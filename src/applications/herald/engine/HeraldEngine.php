<?php

final class HeraldEngine {

  protected $rules = array();
  protected $results = array();
  protected $stack = array();
  protected $activeRule = null;

  protected $fieldCache = array();
  protected $object = null;

  public function getRule($id) {
    return idx($this->rules, $id);
  }

  public static function loadAndApplyRules(HeraldAdapter $adapter) {
    $rules = id(new HeraldRuleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withContentTypes(array($adapter->getAdapterContentType()))
      ->needConditionsAndActions(true)
      ->needAppliedToPHIDs(array($adapter->getPHID()))
      ->needValidateAuthors(true)
      ->execute();

    $engine = new HeraldEngine();
    $effects = $engine->applyRules($rules, $adapter);
    $engine->applyEffects($effects, $adapter, $rules);

    return $engine->getTranscript();
  }

  public function applyRules(array $rules, HeraldAdapter $object) {
    assert_instances_of($rules, 'HeraldRule');
    $t_start = microtime(true);

    $rules = mpull($rules, null, 'getID');

    $this->transcript = new HeraldTranscript();
    $this->transcript->setObjectPHID((string)$object->getPHID());
    $this->fieldCache = array();
    $this->results = array();
    $this->rules   = $rules;
    $this->object  = $object;

    $effects = array();
    foreach ($rules as $id => $rule) {
      $this->stack = array();
      try {
        if (($rule->getRepetitionPolicy() ==
             HeraldRepetitionPolicyConfig::FIRST) &&
            $rule->getRuleApplied($object->getPHID())) {
          // This rule is only supposed to be applied a single time, and it's
          // aleady been applied, so this is an automatic failure.
          $xscript = id(new HeraldRuleTranscript())
            ->setRuleID($id)
            ->setResult(false)
            ->setRuleName($rule->getName())
            ->setRuleOwner($rule->getAuthorPHID())
            ->setReason(
              "This rule is only supposed to be repeated a single time, ".
              "and it has already been applied.");
          $this->transcript->addRuleTranscript($xscript);
          $rule_matches = false;
        } else {
          $rule_matches = $this->doesRuleMatch($rule, $object);
        }
      } catch (HeraldRecursiveConditionsException $ex) {
        $names = array();
        foreach ($this->stack as $rule_id => $ignored) {
          $names[] = '"'.$rules[$rule_id]->getName().'"';
        }
        $names = implode(', ', $names);
        foreach ($this->stack as $rule_id => $ignored) {
          $xscript = new HeraldRuleTranscript();
          $xscript->setRuleID($rule_id);
          $xscript->setResult(false);
          $xscript->setReason(
            "Rules {$names} are recursively dependent upon one another! ".
            "Don't do this! You have formed an unresolvable cycle in the ".
            "dependency graph!");
          $xscript->setRuleName($rules[$rule_id]->getName());
          $xscript->setRuleOwner($rules[$rule_id]->getAuthorPHID());
          $this->transcript->addRuleTranscript($xscript);
        }
        $rule_matches = false;
      }
      $this->results[$id] = $rule_matches;

      if ($rule_matches) {
        foreach ($this->getRuleEffects($rule, $object) as $effect) {
          $effects[] = $effect;
        }
      }
    }

    $object_transcript = new HeraldObjectTranscript();
    $object_transcript->setPHID($object->getPHID());
    $object_transcript->setName($object->getHeraldName());
    $object_transcript->setType($object->getAdapterContentType());
    $object_transcript->setFields($this->fieldCache);

    $this->transcript->setObjectTranscript($object_transcript);

    $t_end = microtime(true);

    $this->transcript->setDuration($t_end - $t_start);

    return $effects;
  }

  public function applyEffects(
    array $effects,
    HeraldAdapter $object,
    array $rules) {
    assert_instances_of($effects, 'HeraldEffect');
    assert_instances_of($rules, 'HeraldRule');

    $this->transcript->setDryRun((int)($object instanceof HeraldDryRunAdapter));

    $xscripts = $object->applyHeraldEffects($effects);
    foreach ($xscripts as $apply_xscript) {
      if (!($apply_xscript instanceof HeraldApplyTranscript)) {
        throw new Exception(
          "Heraldable must return HeraldApplyTranscripts from ".
          "applyHeraldEffect().");
      }
      $this->transcript->addApplyTranscript($apply_xscript);
    }

    if (!$this->transcript->getDryRun()) {

      $rules = mpull($rules, null, 'getID');
      $applied_ids = array();
      $first_policy = HeraldRepetitionPolicyConfig::toInt(
        HeraldRepetitionPolicyConfig::FIRST);

      // Mark all the rules that have had their effects applied as having been
      // executed for the current object.
      $rule_ids = mpull($xscripts, 'getRuleID');

      foreach ($rule_ids as $rule_id) {
        if (!$rule_id) {
          // Some apply transcripts are purely informational and not associated
          // with a rule, e.g. carryover emails from earlier revisions.
          continue;
        }

        $rule = idx($rules, $rule_id);
        if (!$rule) {
          continue;
        }

        if ($rule->getRepetitionPolicy() == $first_policy) {
          $applied_ids[] = $rule_id;
        }
      }

      if ($applied_ids) {
        $conn_w = id(new HeraldRule())->establishConnection('w');
        $sql = array();
        foreach ($applied_ids as $id) {
          $sql[] = qsprintf(
            $conn_w,
            '(%s, %d)',
            $object->getPHID(),
            $id);
        }
        queryfx(
          $conn_w,
          'INSERT IGNORE INTO %T (phid, ruleID) VALUES %Q',
          HeraldRule::TABLE_RULE_APPLIED,
          implode(', ', $sql));
      }
    }
  }

  public function getTranscript() {
    $this->transcript->save();
    return $this->transcript;
  }

  public function doesRuleMatch(
    HeraldRule $rule,
    HeraldAdapter $object) {

    $id = $rule->getID();

    if (isset($this->results[$id])) {
      // If we've already evaluated this rule because another rule depends
      // on it, we don't need to reevaluate it.
      return $this->results[$id];
    }

    if (isset($this->stack[$id])) {
      // We've recursed, fail all of the rules on the stack. This happens when
      // there's a dependency cycle with "Rule conditions match for rule ..."
      // conditions.
      foreach ($this->stack as $rule_id => $ignored) {
        $this->results[$rule_id] = false;
      }
      throw new HeraldRecursiveConditionsException();
    }

    $this->stack[$id] = true;

    $all = $rule->getMustMatchAll();

    $conditions = $rule->getConditions();

    $result = null;

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      $reason = "Rule could not be processed, it was created with a newer ".
                "version of Herald.";
      $result = false;
    } else if (!$conditions) {
      $reason = "Rule failed automatically because it has no conditions.";
      $result = false;
    } else if (!$rule->hasValidAuthor()) {
      $reason = "Rule failed automatically because its owner is invalid ".
                "or disabled.";
      $result = false;
    } else {
      foreach ($conditions as $condition) {
        $match = $this->doesConditionMatch($rule, $condition, $object);

        if (!$all && $match) {
          $reason = "Any condition matched.";
          $result = true;
          break;
        }

        if ($all && !$match) {
          $reason = "Not all conditions matched.";
          $result = false;
          break;
        }
      }

      if ($result === null) {
        if ($all) {
          $reason = "All conditions matched.";
          $result = true;
        } else {
          $reason = "No conditions matched.";
          $result = false;
        }
      }
    }

    $rule_transcript = new HeraldRuleTranscript();
    $rule_transcript->setRuleID($rule->getID());
    $rule_transcript->setResult($result);
    $rule_transcript->setReason($reason);
    $rule_transcript->setRuleName($rule->getName());
    $rule_transcript->setRuleOwner($rule->getAuthorPHID());

    $this->transcript->addRuleTranscript($rule_transcript);

    return $result;
  }

  protected function doesConditionMatch(
    HeraldRule $rule,
    HeraldCondition $condition,
    HeraldAdapter $object) {

    $object_value = $this->getConditionObjectValue($condition, $object);
    $test_value   = $condition->getValue();

    $cond = $condition->getFieldCondition();

    $transcript = new HeraldConditionTranscript();
    $transcript->setRuleID($rule->getID());
    $transcript->setConditionID($condition->getID());
    $transcript->setFieldName($condition->getFieldName());
    $transcript->setCondition($cond);
    $transcript->setTestValue($test_value);

    try {
      $result = $object->doesConditionMatch(
        $this,
        $rule,
        $condition,
        $object_value);
    } catch (HeraldInvalidConditionException $ex) {
      $result = false;
      $transcript->setNote($ex->getMessage());
    }

    $transcript->setResult($result);

    $this->transcript->addConditionTranscript($transcript);

    return $result;
  }

  protected function getConditionObjectValue(
    HeraldCondition $condition,
    HeraldAdapter $object) {

    $field = $condition->getFieldName();

    return $this->getObjectFieldValue($field);
  }

  public function getObjectFieldValue($field) {
    if (isset($this->fieldCache[$field])) {
      return $this->fieldCache[$field];
    }

    $result = $this->object->getHeraldField($field);

    $this->fieldCache[$field] = $result;
    return $result;
  }

  protected function getRuleEffects(
    HeraldRule $rule,
    HeraldAdapter $object) {

    $effects = array();
    foreach ($rule->getActions() as $action) {
      $effect = new HeraldEffect();
      $effect->setObjectPHID($object->getPHID());
      $effect->setAction($action->getAction());
      $effect->setTarget($action->getTarget());

      $effect->setRuleID($rule->getID());

      $name = $rule->getName();
      $id   = $rule->getID();
      $effect->setReason(
        'Conditions were met for Herald rule "'.$name.'" (#'.$id.').');

      $effects[] = $effect;
    }
    return $effects;
  }

}
