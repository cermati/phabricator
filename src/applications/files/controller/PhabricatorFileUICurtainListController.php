<?php

final class PhabricatorFileUICurtainListController
  extends PhabricatorFileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $object_phid = $request->getURIData('phid');

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $view_capability = PhabricatorPolicyCapability::CAN_VIEW;
    $object_policies = PhabricatorPolicyQuery::loadPolicies(
      $viewer,
      $object);
    $object_policy = idx($object_policies, $view_capability);

    $attachments = id(new PhabricatorFileAttachmentQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->needFiles(true)
      ->execute();

    $handles = $viewer->loadHandles(array($object_phid));
    $object_handle = $handles[$object_phid];

    $file_phids = mpull($attachments, 'getFilePHID');
    $file_handles = $viewer->loadHandles($file_phids);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($attachments as $attachment) {
      $file = $attachment->getFile();

      $attach_uri = null;

      if (!$attachment->isPolicyAttachment()) {
        $file_policies = PhabricatorPolicyQuery::loadPolicies(
          $viewer,
          $file);
        $file_policy = idx($file_policies, $view_capability);

        if ($object_policy->isStrongerThanOrEqualTo($file_policy)) {
          // The file is not attached to the object, but the file policy
          // allows anyone who can see the object to see the file too, so
          // there is no material problem with the file not being attached.
        } else {
          $attach_uri = urisprintf(
            '/file/ui/curtain/attach/%s/%s/',
            $object->getPHID(),
            $file->getPHID());
        }
      }
      $file_phid = $attachment->getFilePHID();
      $handle = $file_handles[$file_phid];

      $item = id(new PHUIObjectItemView())
        ->setHeader($handle->getFullName())
        ->setHref($handle->getURI())
        ->setDisabled($handle->isDisabled())
        ->setAttachURI($attach_uri);

      if ($handle->getImageURI()) {
        $item->setImageURI($handle->getImageURI());
      }

      $list->addItem($item);
    }

    return $this->newDialog()
      ->setViewer($viewer)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Referenced Files'))
      ->setObjectList($list)
      ->addCancelButton($object_handle->getURI(), pht('Close'));
  }

}
