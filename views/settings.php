<?php defined('APPLICATION') or die;
echo $this->Form->Open();
echo $this->Form->Errors();
?>

<h1><?= $this->Data('Title') ?></h1>
<div class="Info"><?= $this->Data('Description') ?></div>
<div class="Info"><?= T('Mark required fields for offline verification') ?></div>
<?= $this->Form->CheckBoxList('Plugins.OfflineVerification.Fields', $this->Data('ProfileExtenderFields'), $this->Data('VerificationFields')) ?>
<hr />
<div class="Info"><?= T('Which role should be applied after confirmation?') ?></div>
<?= $this->Form->DropDown('Plugins.OfflineVerification.Role', $this->Data('AllRoles'), array('value' => $this->Data('VerificationRole'))) ?>


<?= $this->Form->Close('Save') ?>