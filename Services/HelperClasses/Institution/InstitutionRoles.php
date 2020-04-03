<?php

require_once("Exceptions/InstitutionRolesInvalidStatement.php");
require_once("Exceptions/InstitutionRolesInvalidAction.php");
require_once("InstitutionActions.php");
require_once("InstitutionValidator.php");

class InstitutionRoles{

    public static function isUserAuthorized($username, $institutionName, $action){

        InstitutionValidator::validateInstitution($institutionName);

        $rightsDictionary = self::fetchUserRightsDictionary($username, $institutionName);

        if($rightsDictionary == null){
            return false;
        }

        switch($action){
            case InstitutionActions::ADD_ROLE :                             return $rightsDictionary['Can_Add_Roles'];
            case InstitutionActions::DELETE_ROLE :                          return $rightsDictionary['Can_Remove_Roles'];
            case InstitutionActions::MODIFY_ROLE :                          return $rightsDictionary['Can_Modify_Roles'];
            case InstitutionActions::ASSIGN_ROLE :                          return $rightsDictionary['Can_Assign_Roles'];
            case InstitutionActions::DEASSIGN_ROLE :                        return $rightsDictionary['Can_Deassign_Roles'];
            case InstitutionActions::MODIFY_INSTITUTION :                   return $rightsDictionary['Can_Modify_Institution'];
            case InstitutionActions::DELETE_INSTITUTION :                   return $rightsDictionary['Can_Delete_Institution'];
            case InstitutionActions::ADD_MEMBERS :                          return $rightsDictionary['Can_Add_Members'];
            case InstitutionActions::REMOVE_MEMBERS :                       return $rightsDictionary['Can_Remove_Members'];
            case InstitutionActions::UPLOAD_DOCUMENTS :                     return $rightsDictionary['Can_Upload_Documents'];
            case InstitutionActions::PREVIEW_UPLOADED_DOCUMENTS :           return $rightsDictionary['Can_Preview_Uploaded_Documents'];
            case InstitutionActions::REMOVE_UPLOADED_DOCUMENTS :            return $rightsDictionary['Can_Remove_Uploaded_Documents'];
            case InstitutionActions::SEND_DOCUMENTS :                       return $rightsDictionary['Can_Send_Documents'];
            case InstitutionActions::PREVIEW_RECEIVED_DOCUMENTS :           return $rightsDictionary['Can_Preview_Received_Documents'];
            case InstitutionActions::PREVIEW_SPECIFIC_RECEIVED_DOCUMENT :   return $rightsDictionary['Can_Preview_Specific_Received_Document'];
            case InstitutionActions::REMOVE_RECEIVED_DOCUMENTS :            return $rightsDictionary['Can_Remove_Received_Documents'];
            case InstitutionActions::DOWNLOAD_DOCUMENTS :                   return $rightsDictionary['Can_Download_Documents'];

            default : throw new InstitutionRolesInvalidAction("Invalid Action");
        }

    }

    public static function fetchUserRightsDictionary($username, $institutionName){

        InstitutionValidator::validateInstitution($institutionName);

        try {
            DatabaseManager::Connect();

            $institutionID = InstitutionValidator::getLastValidatedInstitution()->getID();

            $SQLStatement = DatabaseManager::PrepareStatement(self::$fetchRightsForUserInInstitution);
            $SQLStatement->bindParam(":username", $username);
            $SQLStatement->bindParam(":institutionID", $institutionID);
            $SQLStatement->execute();

            $rightsDictionary = $SQLStatement->fetch(PDO::FETCH_ASSOC);

            DatabaseManager::Disconnect();

            return $rightsDictionary;
        }
        catch(Exception $databaseException){
            $response = CommonEndPointLogic::GetFailureResponseStatus("DB_EXCEPT");

            echo json_encode($response), PHP_EOL;
            http_response_code(StatusCodes::OK);
            die();
        }
    }

    public static function getRoleID($roleName, $institutionName){

        InstitutionValidator::validateInstitution($institutionName);

        try{
            DatabaseManager::Connect();

            $institutionID = InstitutionValidator::getLastValidatedInstitution()->getID();

            $SQLStatement = DatabaseManager::PrepareStatement(self::$getRoleIDStatement);
            $SQLStatement->bindParam(":institutionID", $institutionID);
            $SQLStatement->bindParam(":title", $roleName);

            $SQLStatement->execute();

            $row = $SQLStatement->fetch(PDO::FETCH_OBJ);

            DatabaseManager::Disconnect();

            print_r($row);

            if($row == null)
                return null;

            return $row->ID;
        }
        catch(Exception $exception){
            $response = CommonEndPointLogic::GetFailureResponseStatus("DB_EXCEPT");

            echo json_encode($response), PHP_EOL;
            http_response_code(StatusCodes::OK);

            die();
        }
    }

    public static function createRole($roleName, $institutionName, $newRoleRightsDictionary){

        InstitutionValidator::validateInstitution($institutionName);

        if(self::getRoleID($roleName,$institutionName) != null){
            $response = CommonEndPointLogic::GetFailureResponseStatus("DUPLICATE_ROLE");

            echo json_encode($response), PHP_EOL;
            http_response_code(StatusCodes::OK);

            die();
        }

        $rightsID = self::fetchRightsID($newRoleRightsDictionary);

        try{
            DatabaseManager::Connect();

            $institutionID = InstitutionValidator::getLastValidatedInstitution()->getID();

            $SQLStatement = DatabaseManager::PrepareStatement(self::$insertNewRoleStatement);
            $SQLStatement->bindParam(":institutionID", $institutionID);
            $SQLStatement->bindParam("institutionRightsID", $rightsID);
            $SQLStatement->bindParam(":title", $roleName);

            $SQLStatement->execute();

            if($SQLStatement->rowCount() == 0){
                $response = CommonEndPointLogic::GetFailureResponseStatus("ROLE_DUPLICATE_SAME_RIGHTS");

                echo json_encode($response), PHP_EOL;
                http_response_code(StatusCodes::OK);
                die();
            }

            DatabaseManager::Disconnect();
        }
        catch(Exception $exception){
            $response = CommonEndPointLogic::GetFailureResponseStatus("DB_EXCEPT");

            echo json_encode($response), PHP_EOL;
            http_response_code(StatusCodes::OK);

            die();
        }

    }

    public static function fetchRightsID($rightsDictionary){

        DatabaseManager::Connect();

        try{
            $SQLStatement = InstitutionRoles::generateRightsStatement(InstitutionRoles::GENERATE_RIGHTS_FETCH_ID_STATEMENT, $rightsDictionary);
            $SQLStatement->execute();
            $row = $SQLStatement->fetch(PDO::FETCH_OBJ);

            if($row == null){
                $maxIDStatement = DatabaseManager::PrepareStatement(self::$fetchMaxIDStatement);
                $maxIDStatement->execute();

                $maxIDRow = $maxIDStatement->fetch();
                $ID = $maxIDRow[0] + 1;

                $SQLStatement = InstitutionRoles::generateRightsStatement(self::GENERATE_RIGHTS_INSERT_NEW_ROW_STATEMENT, $rightsDictionary);
                $SQLStatement->bindParam(":ID", $ID);
                $SQLStatement->execute();
            }
            else{
                $ID = $row->ID;
            }
        }
        catch(InstitutionRolesInvalidStatement $exception){
            $response = CommonEndPointLogic::GetFailureResponseStatus("DB_EXCEPT");

            echo json_encode($response), PHP_EOL, $exception->getMessage(), PHP_EOL;
            http_response_code(StatusCodes::OK);

            die();
        }

        DatabaseManager::Disconnect();

        return $ID;
    }

    private static function generateRightsStatement($statementType, $rightsDictionary){
        switch($statementType) {
            case self::GENERATE_RIGHTS_FETCH_ID_STATEMENT :
                $SQLStatement = DatabaseManager::PrepareStatement(self::$fetchRightsIDStatement);
                break;

            case self::GENERATE_RIGHTS_INSERT_NEW_ROW_STATEMENT :
                $SQLStatement = DatabaseManager::PrepareStatement(self::$insertRightsStatement);
                break;

            default: throw new InstitutionRolesInvalidStatement("Incorrect Statement");
        }

        $SQLStatement->bindParam(':canModifyInstitution',               $rightsDictionary['Can_Modify_Institution'],                    PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canDeleteInstitution',               $rightsDictionary['Can_Delete_Institution'],                    PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canAddMembers',                      $rightsDictionary['Can_Add_Members'],                           PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canRemoveMembers',                   $rightsDictionary['Can_Remove_Members'],                        PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canUploadDocuments',                 $rightsDictionary['Can_Upload_Documents'],                      PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canPreviewUploadedDocuments',        $rightsDictionary['Can_Preview_Uploaded_Documents'],            PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canRemoveUploadedDocuments',         $rightsDictionary['Can_Remove_Uploaded_Documents'],             PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canSendDocuments',                   $rightsDictionary['Can_Send_Documents'],                        PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canPreviewReceivedDocuments',        $rightsDictionary['Can_Preview_Received_Documents'],            PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canPreviewSpecificReceivedDocument', $rightsDictionary['Can_Preview_Specific_Received_Document'],    PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canRemoveReceivedDocuments',         $rightsDictionary['Can_Remove_Received_Documents'],             PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canDownloadDocuments',               $rightsDictionary['Can_Download_Documents'],                    PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canAddRoles',                        $rightsDictionary['Can_Add_Roles'],                             PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canRemoveRoles',                     $rightsDictionary['Can_Remove_Roles'],                          PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canModifyRoles',                     $rightsDictionary['Can_Modify_Roles'],                          PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canAssignRoles',                     $rightsDictionary['Can_Assign_Roles'],                          PDO::PARAM_BOOL);
        $SQLStatement->bindParam(':canDeassignRoles',                   $rightsDictionary['Can_Deassign_Roles'],                        PDO::PARAM_BOOL);
        return $SQLStatement;
    }

    const GENERATE_RIGHTS_FETCH_ID_STATEMENT = 0;
    const GENERATE_RIGHTS_INSERT_NEW_ROW_STATEMENT = 1;

    private static $getRoleIDStatement = "
        SELECT ID FROM institution_roles WHERE Institution_ID = :institutionID AND Title = :title
    ";

    private static $insertNewRoleStatement = "
        INSERT INTO institution_roles (Institution_ID, Institution_Rights_ID, Title)
            value
            (:institutionID, :institutionRightsID, :title)
    ";

    private static $fetchMaxIDStatement = "
        SELECT MAX(ID) FROM institution_rights;    
    ";

    private static $fetchRightsForUserInInstitution = "
        SELECT
            Can_Modify_Institution,
            Can_Delete_Institution,
            Can_Add_Members,
            Can_Remove_Members,
            Can_Upload_Documents,
            Can_Preview_Uploaded_Documents,
            Can_Remove_Uploaded_Documents,
            Can_Send_Documents,
            Can_Preview_Received_Documents,
            Can_Preview_Specific_Received_Document,
            Can_Remove_Received_Documents,
            Can_Download_Documents,
            Can_Add_Roles,
            Can_Remove_Roles,
            Can_Modify_Roles,
            Can_Assign_Roles,
            Can_Deassign_Roles
        FROM institution_rights WHERE ID = (
            SELECT Institution_Rights_ID FROM institution_roles WHERE ID = (
                SELECT Institution_Roles_ID FROM institution_members WHERE User_ID = (
                    SELECT ID FROM users WHERE Username = :username
                )
                AND Institution_ID = :institutionID
            )
)
    ";

    private static $fetchRightsIDStatement = "
        SELECT ID FROM institution_rights where
            Can_Modify_Institution = :canModifyInstitution AND
            Can_Delete_Institution = :canDeleteInstitution AND
            Can_Add_Members = :canAddMembers AND
            Can_Remove_Members = :canRemoveMembers AND 
            Can_Upload_Documents = :canUploadDocuments AND
            Can_Preview_Uploaded_Documents = :canPreviewUploadedDocuments AND
            Can_Remove_Uploaded_Documents = :canRemoveUploadedDocuments AND
            Can_Send_Documents = :canSendDocuments AND
            Can_Preview_Received_Documents = :canPreviewReceivedDocuments AND
            Can_Preview_Specific_Received_Document = :canPreviewSpecificReceivedDocument AND
            Can_Remove_Received_Documents = :canRemoveReceivedDocuments AND
            Can_Download_Documents = :canDownloadDocuments AND
            Can_Add_Roles = :canAddRoles AND
            Can_Remove_Roles = :canRemoveRoles AND
            Can_Modify_Roles = :canModifyRoles AND
            Can_Assign_Roles = :canAssignRoles AND
            Can_Deassign_Roles = :canDeassignRoles
        ";

    private static $insertRightsStatement = "
        INSERT INTO institution_rights (
            ID,
            Can_Modify_Institution,
            Can_Delete_Institution,
            Can_Add_Members,
            Can_Remove_Members,
            Can_Upload_Documents,
            Can_Preview_Uploaded_Documents,
            Can_Remove_Uploaded_Documents,
            Can_Send_Documents,
            Can_Preview_Received_Documents,
            Can_Preview_Specific_Received_Document,
            Can_Remove_Received_Documents,
            Can_Download_Documents,
            Can_Add_Roles,
            Can_Remove_roles,
            Can_Modify_Roles,
            Can_Assign_Roles,
            Can_Deassign_Roles
        ) value (
            :ID,
            :canModifyInstitution, 
            :canDeleteInstitution,
            :canAddMembers,
            :canRemoveMembers,
            :canUploadDocuments,
            :canPreviewUploadedDocuments,
            :canRemoveUploadedDocuments,
            :canSendDocuments,
            :canPreviewReceivedDocuments,
            :canPreviewSpecificReceivedDocument,
            :canRemoveReceivedDocuments,
            :canDownloadDocuments,
            :canAddRoles,
            :canRemoveRoles,
            :canModifyRoles,
            :canAssignRoles,
            :canDeassignRoles
        )
    ";

}

?>