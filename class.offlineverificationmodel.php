<?php defined('APPLICATION') or die;

class OfflineVerificationModel extends Gdn_Model {
    public function __construct($Name = '') {
        parent::__construct('OfflineVerification');
$this->DefineSchema(); // prüfen, ob vllt überflüssig
    }
}
