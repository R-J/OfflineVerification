<?php defined('APPLICATION') or die;

$PluginInfo['OfflineVerification'] = array(
    'Name' => 'Offline Verification',
    'Description' => 'Creates custom user fields for postal address and creates a unique key that has to be validated in order to allow users special roles',
    'Version' => '0.1',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('ProfileExtender' => '3'),
    'SettingsUrl' => '/dashboard/settings/offlineverification',
    'SettingsPermission' => 'Garden.Settings.Manage',    
    'MobileFriendly' => true,
    'Author' => 'Robin Jurinka',
    'License' => 'MIT'
);

/**

 */
class OfflineVerificationPlugin extends Gdn_Plugin {
    private $_VerificationFields = array();
    private $_VerificationRole = 0;
    private $_VerificationMaxTries = 10;
    
    /**
     *  When class is loaded, set some value for better performance
     */
    public function __construct() {
        $this->_VerificationFields = C('Plugins.OfflineVerification.Fields', array());
        $this->_VerificationRole = C('Plugins.OfflineVerification.Role', 0);
        $this->_VerificationMaxTries = C('Plugins.OfflineVerification.MaxTries', 10);
    }
    
    
    /**
     *  When plugin is enabled, change db structure and init config vars.
     */
    public function Setup() {
        $this->Structure();
        if (C('Plugins.OfflineVerification.MaxTries', 10) == False) {
            SaveToConfig('Plugins.OfflineVerification.MaxTries', 10);
        }
    }

    /**
     *  Add table for verification info.
     */
    public function Structure() {
        Gdn::Structure()->Table('OfflineVerification')
            ->Engine('InnoDB')
            ->PrimaryKey('OfflineVerificationID')
            ->Column('UserID', 'int(11)', False, 'unique')
            ->Column('LetterReady', 'tinyint(1)', '0') // should be set when all criterias are met to send user a letter
            ->Column('LetterSent', 'tinyint(1)', '0') // flag for easier management of who already received a letter
            ->Column('VerificationKey', 'varchar(64)', '') // random key needed for verification
            ->Column('InvalidVerificationCount', 'int(11)', 0) // 
            ->Set();
    }
    
    /**
     *  Handles the setting of required profile fields and after verification role.
     */
    public function SettingsController_OfflineVerification_Create ($Sender) {
        // verification check
        $Sender->Permission('Garden.Settings.Manage');
        
        // after submit, set config values
        if ($Sender->Form->IsPostBack()) {
            $FormPostValues = $Sender->Form->FormValues();
            SaveToConfig('Plugins.OfflineVerification.Fields', $FormPostValues['Plugins.OfflineVerification.Fields']);
            SaveToConfig('Plugins.OfflineVerification.Role', $FormPostValues['Plugins.OfflineVerification.Role']);
        }
        
        // get all profile extender fields
        $FullProfileExtenderFields = $this->GetProfileFields();
        $ProfileExtenderFields = array();
        foreach ($FullProfileExtenderFields as $Key => $Value) {
            $ProfileExtenderFields[$FullProfileExtenderFields[$Key]['Label']] = $Key;
        }
        $Sender->SetData('ProfileExtenderFields', $ProfileExtenderFields);
        
        // if already set, get fields needed for verification letter
        $OfflineVerificationFields = C('Plugins.OfflineVerification.Fields', array());
        if (!is_array($OfflineVerificationFields)) {
            $OfflineVerificationFields = array();
        }
        $Sender->SetData('VerificationFields', C('Plugins.OfflineVerification.Fields', array()));
        
        // get all roles and role that will be promoted to after verification
        $RoleModel = new RoleModel();
        $Sender->SetData('AllRoles', $RoleModel->GetArray());
        $Sender->SetData('VerificationRole', C('Plugins.OfflineVerification.Role', ''));

        // set data needed for settings view
        $Sender->AddSideMenu('settings/offlineverification');
        $Sender->SetData('Title', T('SettingsTitle', $this->GetPluginKey('Name')));
        $Sender->SetData('Description', T('SettingsDescription', $this->GetPluginKey('Description')));
        
        $Sender->Render('settings', '', 'plugins/OfflineVerification');
    }

    
    /**
     * Get custom profile fields (copied from plugin ProfileExtender)
     *
     * @return array
     */
    private function GetProfileFields() {
        $Fields = Gdn::Config('ProfileExtender.Fields', array());

        if (!is_array($Fields)) {
            $Fields = array();
        }

        // Data checks
        foreach ($Fields as $Name => $Field) {
            // Require an array for each field
            if (!is_array($Field) || strlen($Name) < 1) {
                unset($Fields[$Name]);
                //RemoveFromConfig('ProfileExtender.Fields.'.$Name);
            }

            // Verify field form type
            if (!isset($Field['FormType'])) {
                $Fields[$Name]['FormType'] = 'TextBox';
            } elseif (!array_key_exists($Field['FormType'], $this->FormTypes)) {
                unset($this->ProfileFields[$Name]);
            }
        }

        // Special case for birthday field
        if (isset($Fields['DateOfBirth'])) {
            $Fields['DateOfBirth']['FormType'] = 'Date';
            $Fields['DateOfBirth']['Label'] = T('Birthday');
        }

        return $Fields;
    }


    /**
     *  Add menu item to dashboard for members list.
     */
    public function Base_GetAppSettingsMenuItems_Handler($Sender) {
        $Menu = &$Sender->EventArguments['SideMenu'];
        $Menu->AddLink('Users', T('Offline Verification'), 'plugin/offlineverification', 'Garden.Settings.Manage');
    }
    
    /**
     *  Dispatcher for PluginController so that it can be used for multiple methods.
     */
    public function PluginController_OfflineVerification_Create ($Sender) {
        $this->Dispatch($Sender, $Sender->RequestArgs);
    }
    
    /**
     *  Show list of users who a) are ready for letter serving and b) already have a letter.
     *  Only show users who are not in the role a verified user shall be promoted to.
     *  Also show button for downloading a CSV file.
     */
    public function Controller_Index($Sender) {
        // check permission
        $Sender->Permission('Garden.Settings.Manage');
        
        // add view information
        $Sender->MasterView = 'admin';
        $Sender->AddSideMenu('plugin/offlineverification');    
        $Sender->SetData('Title', T('UserListTitle', 'Offline Verification User List'));
        $Sender->SetData('Description', T('UserListDescription', 'Mark all the users that already received a letter'));
        
        // attach form and save "LetterSent" information on submit
        $Sender->Form = new Gdn_Form();
        if ($Sender->Form->IsPostBack()) {
            $FormPostValues = $Sender->Form->FormValues();
decho($FormPostValues);
            foreach ($FormPostValues as $Key => $Value) {
                if (substr($Key, 0, 11) == 'LetterSent_') {
                    // decho(substr($Key, 11, 10));
                    // decho($Value);
                }
            }
            // für alle user (aus ursprünglicher liste!), speichere LetterSent (Validation, dass LetterSent 0 oder 1 ist)
        }

        // get all users who already are in promoted role
        $Sql = Gdn::SQL();
        $UsersWithVerificationRole = $Sql->Select('UserID')
            ->From('UserRole')
            ->Where('RoleID', $this->_VerificationRole)
            ->Get()
            ->ResultArray();
        $UsersWithVerificationRole = ConsolidateArrayValuesByKey($UsersWithVerificationRole, 'UserID');

        // get all users not in promoted role and ready to receive a letter
        $Users = $Sql->Select('u.UserID, u.Name, u.Email, ov.LetterSent, ov.VerificationKey')
            ->From('User u')
            ->Join('OfflineVerification ov', 'u.UserID = ov.UserID')
            ->Where('ov.LetterReady', 1)
            ->WhereNotIn('u.UserID', $UsersWithVerificationRole)
            ->OrderBy('u.UserID')
            ->Get()
            ->ResultArray();

        // mix in the profile fields needed for verification
        $UserMetaModel = new UserMetaModel();
        $UserMeta = $UserMetaModel->GetUserMeta(ConsolidateArrayValuesByKey($Users, 'UserID'), 'Profile.%', '');
        foreach ($Users as &$User) {
            $UserID = $User['UserID'];
            foreach ($this->_VerificationFields as $Field) {
                $User[$Field] = $UserMeta[$UserID]['Profile.'.$Field];
            }
        }

        // set view data
        $Sender->SetData('Users', $Users);
        $Sender->Render('userlist', '', 'plugins/OfflineVerification');
        // tabelle, mit allen informationen im Array
        // Besonderheiten:
        // UserID erzeugt keine Spalte
        // LetterSent erzeugt eine Checkbox deren Name die UserID beinhaltet
    }
    
    public function Controller_Export ($Sender) {
        // export as csv
    }
    
    public function ProfileController_OfflineVerification_Create ($Sender, $Args) {
        // test key against db.
        // count tries.
        // invalidate key and reset key after X tries
    }


    /**
     *  Mark all users who have completed all address fields
     */
    public function UserModel_AfterSave_Handler ($Sender) {
        $UserID = val('UserID', $Sender->EventArguments['User']);

        // check if already in target role
        $RoleModel = new RoleModel();
        $Roles = $RoleModel->GetByUserID($UserID)->ResultArray();
        foreach ($Roles as $Role) {
            if ($Role['RoleID'] == $this->_VerificationRole) {
                // if yes, we have nothing to do
                return;
            }
        }
        
        // check if a letter has already been sent
        $Sql = Gdn::SQL();
        $OfflineVerification = $Sql->GetWhere('OfflineVerification', array('UserID' => $UserID));
        if ($OfflineVerification->LetterSent == true) {
            // if yes, we have nothing to do
            return;
        }
        
        
        // check if all required fields are filled
        $UserMetaModel = new UserMetaModel();
        $ProfileExtenderFields = $UserMetaModel->GetUserMeta($UserID, 'Profile.%', '');
        $CompleteFilled = true;
        foreach ($this->_VerificationFields as $Field) {
            if (strlen(trim($ProfileExtenderFields['Profile.'.$Field])) == 0) {
                $CompleteFilled = false;
            }
        }

        // if not, quit
        if (!$CompleteFilled) {
            return;
        }

        // finally, if all checks are passed, set info that letter can be send
        $Sql->Replace('OfflineVerification', // table
            array(
                'LetterReady' => 1,
                'VerificationKey' => $this->_CreateRandomKey
            ), // set  
            array('UserID' => $UserID), //where
            true // insert or update
        );
    }
    
    private function _CreateRandomKey($AllowedCharacters = 'abcdefghijklmnopqrstuvwxyz0123456789_-', $KeyLength = 16) {
        $CharactersCount = strlen($AllowedCharacters);
        $Key = '';
        while( $KeyLength-- )
        {
           $Key .= $AllowedCharacters[rand(0,$CharactersCount)];
        }
        return urlencode($Key);
    }
}

