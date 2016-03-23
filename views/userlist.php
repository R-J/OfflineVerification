<?php defined('APPLICATION') or die;
echo $this->Form->Open();
echo $this->Form->Errors();
$Users = $this->Data('Users');
?>

<h1><?= $this->Data('Title') ?></h1>
<div class="Info"><?= $this->Data('Description') ?></div>
<table>
	<thead>
		<tr>
			<th></th>
<?php
    foreach ($Users[0] as $Key => $Value) {
        if ($Key != 'LetterSent') {
            echo Wrap($Key, 'th');
        }
    }
?>
		</tr>
	</thead>
    <tbody>
<?php foreach ($Users as $User): ?>
    	<tr>
    		<td>
                <?= $this->Form->CheckBox('LetterSent_'.$User['UserID'], '', array('Value' => 'LetterSent_'.$User['UserID'])) ?>
<?= $User['LetterSent'] ?>
<?= $this->Form->CheckBox('Test') ?>
            </td>
<?php
        foreach ($User as $Key => $Value) {
            if ($Key != 'LetterSent') {
                echo Wrap($Value, 'td');
            }
        }
?>
    	</tr>
<?php endforeach; ?>
    </tbody>
</table>
<?= $this->Form->Close('Save') ?>
