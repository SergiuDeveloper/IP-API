<?php

    if(!defined('ROOT')){
        define('ROOT', dirname(__FILE__) . '/../..');
    }

    require_once(ROOT . "/Utility/Utilities.php");

    require_once(ROOT . "/Institution/Role/Utility/InstitutionActions.php");
    require_once(ROOT . "/Institution/Role/Utility/InstitutionRoles.php");

    CommonEndPointLogic::ValidateHTTPPOSTRequest();

    $email              = $_POST["email"];
    $hashedPassword     = $_POST["hashedPassword"];
    $institutionName    = $_POST["institutionName"];

    $apiKey = $_POST["apiKey"];

    if($apiKey != null){
        try {
            $credentials = APIKeyHandler::getInstance()->setAPIKey($apiKey)->getCredentials();
        } catch (APIKeyHandlerKeyUnbound $e) {
            ResponseHandler::getInstance()
                ->setResponseHeader(CommonEndPointLogic::GetFailureResponseStatus("UNBOUND_KEY"))
                ->send(StatusCodes::INTERNAL_SERVER_ERROR);
        } catch (APIKeyHandlerAPIKeyInvalid $e) {
            ResponseHandler::getInstance()
                ->setResponseHeader(CommonEndPointLogic::GetFailureResponseStatus("INVALID_KEY"))
                ->send();
        }

        $email = $credentials->getEmail();
        //$hashedPassword = $credentials->getHashedPassword();
    } else {
        if ($email == null || $hashedPassword == null) {
            ResponseHandler::getInstance()
                ->setResponseHeader(CommonEndPointLogic::GetFailureResponseStatus("NULL_CREDENTIAL"))
                ->send(StatusCodes::BAD_REQUEST);
        }
        CommonEndPointLogic::ValidateUserCredentials($email, $hashedPassword);
    }

    if ($institutionName == null) {
        ResponseHandler::getInstance()
            ->setResponseHeader(CommonEndPointLogic::GetFailureResponseStatus("NULL_INPUT"))
            ->send(StatusCodes::BAD_REQUEST);
    }

    $queryIdInstitution = "SELECT ID FROM Institutions WHERE name = :institutionName;";

    $queryDeleteContactEmail = "DELETE FROM contact_email_addresses WHERE Institution_ID = :institutionId;";
    $queryDeleteContactPhone = "DELETE FROM contact_phone_numbers WHERE Institution_ID = :institutionId;";
    $queryDeleteContactFax = "DELETE FROM contact_fax_numbers WHERE Institution_ID = :institutionId;";

    try {
        DatabaseManager::Connect();

        $getInstitution = DatabaseManager::PrepareStatement($queryIdInstitution);
        $getInstitution->bindParam(":institutionName", $institutionName);
        $getInstitution->execute();

        $institutionRow = $getInstitution->fetch(PDO::FETCH_ASSOC);

        if($institutionRow == null){
            ResponseHandler::getInstance()
                ->setResponseHeader(CommonEndPointLogic::GetFailureResponseStatus("INSTITUTION_NOT_FOUND"))
                ->send();
                DatabaseManager::Disconnect();
        }

        if( false == InstitutionRoles::isUserAuthorized($email, $institutionName, InstitutionActions::MODIFY_INSTITUTION)) {
            ResponseHandler::getInstance()
                ->setResponseHeader(CommonEndPointLogic::GetFailureResponseStatus("UNAUTHORIZED_ACTION"))
                ->send();
        }
        DatabaseManager::Connect();

        /*$getEmail = DatabaseManager::PrepareStatement($queryContactEmail);
        $getEmail->bindParam(":institutionId", $institutionRow['ID']);
        $getEmail->execute();

        $emailRow = $getEmail->fetch(PDO::FETCH_ASSOC);

        $getPhone = DatabaseManager::PrepareStatement($queryContactPhone);
        $getPhone->bindParam(":institutionId", $institutionRow['ID']);
        $getPhone->execute();

        $phoneRow = $getPhone->fetch(PDO::FETCH_ASSOC);

        $getFax = DatabaseManager::PrepareStatement($queryContactFax);
        $getFax->bindParam(":institutionId", $institutionRow['ID']);
        $getFax->execute();

        $faxRow = $getFax->fetch(PDO::FETCH_ASSOC);

        if($emailRow == null && $phoneRow == null && $faxRow == null){
            ResponseHandler::getInstance()
            ->setResponseHeader(CommonEndPointLogic::GetFailureResponseStatus("CONTACT_NOT_FOUND"))
            ->send();
            DatabaseManager::Disconnect();
        }
        */

        $delete = DatabaseManager::PrepareStatement($queryDeleteContactEmail);
        $delete->bindParam(":institutionId", $institutionRow['ID']);
        $delete->execute();

        $delete = DatabaseManager::PrepareStatement($queryDeleteContactPhone);
        $delete->bindParam(":institutionId", $institutionRow['ID']);
        $delete->execute();

        $delete = DatabaseManager::PrepareStatement($queryDeleteContactFax);
        $delete->bindParam(":institutionId", $institutionRow['ID']);
        $delete->execute();

        DatabaseManager::Disconnect();
    }
    catch (Exception $databaseException) {
        ResponseHandler::getInstance()
            ->setResponseHeader(CommonEndPointLogic::GetFailureResponseStatus("DB_EXCEPT"))
            ->send();
    }

    try {
        ResponseHandler::getInstance()
        ->setResponseHeader(CommonEndPointLogic::GetSuccessResponseStatus())
        ->send();
    }
    catch (Exception $exception){
        ResponseHandler::getInstance()
        ->setResponseHeader(CommonEndPointLogic::GetFailureResponseStatus("INTERNAL_SERVER_ERROR"))
        ->send(StatusCodes::INTERNAL_SERVER_ERROR);
    }
?>