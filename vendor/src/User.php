<?php
namespace CMSLite;
use CMSLite\Database;
use CMSLite\Translate;
use CMSLite\Mailer;

class User{

    public static function register($username, $password, $email){
        $conn = new Database();

        if(ENABLE_REGISTER === false){
            return array(
                "msg" => Translate::text('disabled_register')
            );
        }

        if(ENABLE_MAX_ACCOUNTS_PER_EMAIL === true){
            $countEmails = $conn->count("SELECT email FROM ".ACCOUNT_DB.".account WHERE email = :EMAIL", [
                ":EMAIL" => $email
            ]);

            if($countEmails >= MAX_ACCOUNTS_PER_EMAIL){
                return array(
                    "msg" => Translate::text('err_max_account_email')
                );
            }
        }
       
        $result = $conn->count("SELECT login FROM ".ACCOUNT_DB.".account WHERE login = :USERNAME", [
            ":USERNAME" => $username
        ]);
        
        if($result > 0){
            return array(
                "msg" => Translate::text('username_not_avail')
            );
        }else{
            $result = $conn->count("INSERT INTO ".ACCOUNT_DB.".account (login, password, email, create_time, register_ip) VALUES (:USERNAME, :PASSWORD, :EMAIL, NOW(), :IP)",[
                ":USERNAME" => $username,
                ":PASSWORD" => hashPassword($password),
                ":EMAIL" => $email,
                ":IP" => $_SERVER['REMOTE_ADDR']
            ]);

            if($result > 0){
                return array(
                    "msg" => Translate::text('success_register'),
                    "type" => "success-create"
                );
            }else{
                //??
                return array(
                    "msg" => Translate::text('generic_err_register')
                );
            }
        }
    }

    public static function login($username, $password){
        
        $conn = new Database();

        if(BLOCK_LOGIN_SITE_USER_BAN === true){
            $getBanTime = $conn->select("SELECT status, availDt FROM ".ACCOUNT_DB.".account WHERE login = :LOGIN", [
                ":LOGIN" => $username
            ]);

            if($getBanTime[0]['status'] == 'BAN'){
                generateToken(); 
                return array(
                    "msg" => Translate::text('cant_login_user_banned2'),
                    "__token" => $_SESSION['token']
                );
            }

            $banTimestamp = strtotime($getBanTime[0]['availDt']);
            $currentTimestamp = (new \DateTime())->getTimestamp();

            if($banTimestamp > $currentTimestamp){
                
                $waitTime = (new \DateTime($getBanTime[0]['availDt']))->format('d/m/Y H:i:s');
    
                generateToken(); 
                return array(
                    "msg" => Translate::text('cant_login_user_banned')."$waitTime",
                    "__token" => $_SESSION['token']
                );

            }
        }
        
        $result = $conn->select("SELECT id, login, password FROM ".ACCOUNT_DB.".account WHERE login = :LOGIN", [
            ":LOGIN" => $username
        ]);

        if(count($result) > 0){
            if($result[0]['password'] !== hashPassword($password)){
                generateToken(); 
                return array(
                    "msg" => Translate::text('generic_login_err'),
                    "__token" => $_SESSION['token']
                );
            }else{
                //Let's Login!
                $_SESSION['id'] = $result[0]['id'];
                $_SESSION['username'] = $result[0]['login'];
                
                generateToken(); 
                return array(
                    "redirect" => true,
                );
            }
        }else{
            //??
            generateToken(); 
            return array(
                "msg" => Translate::text('generic_login_err'),
                "__token" => $_SESSION['token']
            );
        }
    }

    //Verifica se o ID conectado pertence ao usuário.
    public static function isUserId(){
        $conn = new Database();
        $result = $conn->count("SELECT id, login FROM ".ACCOUNT_DB.".account WHERE id = :ID and login = :LOGIN", [
            ":ID" => $_SESSION['id'],
            ":LOGIN" => $_SESSION['username']
        ]);

        if($result > 0){
            return true;
        }

        return false;
    }

    public static function isUserEmail($email){
        $conn = new Database();
        $result = $conn->count("SELECT email FROM ".ACCOUNT_DB.".account WHERE id = :ID AND login = :LOGIN AND email = :EMAIL", [
            ":ID" => $_SESSION['id'],
            ":LOGIN" => $_SESSION['username'],
            ":EMAIL" => $email
        ]);

        if($result > 0){
            return true;
        }

        return false;
    }

    public static function isUserPassword($userPassword){
        $conn = new Database();
        $result = $conn->count("SELECT password FROM ".ACCOUNT_DB.".account WHERE id = :ID AND login = :LOGIN AND password = :PASSWORD", [
            ":ID" => $_SESSION['id'],
            ":LOGIN" => $_SESSION['username'],
            ":PASSWORD" => hashPassword($userPassword)
        ]);

        if($result > 0){
            return true;
        }

        return false;
    }

    public static function isValidLogin(){
        if(empty($_SESSION['id']) || !isset($_SESSION['id']) || !(int)$_SESSION['id'] 
        || empty($_SESSION['username']) || !isset($_SESSION['username']) || !(string)$_SESSION['username'] 
        || !self::isUserId()){
            self::logout();
        }

        return true;
    }

    public static function isAdmin(){
        $conn = new Database();
        $result = $conn->select("SELECT web_admin FROM ".ACCOUNT_DB.".account WHERE id = :ID AND login = :LOGIN", [
            ":ID" => $_SESSION['id'],
            ":LOGIN" => $_SESSION['username']
        ]);

        if($result[0]['web_admin'] !== 1){
            return false;
        }
        return true;
    }

    public static function logout(){
        session_destroy();
        header("Location: /");
        exit;
    }

    public static function goHome(){
        header("Location: /");
        exit;
    }

    public static function setMsg($msg = array()){
        $_SESSION['msg'] = $msg;
    }

    public static function getMsg(){
        $msg = (isset($_SESSION['msg'])) ? $_SESSION['msg'] : '';
        self::clearMsg();

        return $msg;
    }

    public static function clearMsg(){
        $_SESSION['msg'] = null;
    }

    public static function changeEmail($newEmail){
        $conn = new Database();

        $result = $conn->count("UPDATE ".ACCOUNT_DB.".account SET email = :NEWEMAIL WHERE id = :ID AND login = :LOGIN", [
            ":NEWEMAIL" => $newEmail,
            ":ID" => $_SESSION['id'],
            ":LOGIN" => $_SESSION['username']
        ]);

        if($result > 0){
            User::setMsg([Translate::text('success_change_email'), "success"]);
            return true;
        }

        User::setMsg([Translate::text('generic_err_change_email'), "failed"]);
        return false;
    }

    public static function changePassword($newPassword){
        $conn = new Database();
        $result = $conn->count("UPDATE ".ACCOUNT_DB.".account SET password = :NEWPASSWORD WHERE id = :ID AND login = :LOGIN", [
            ":NEWPASSWORD" => hashPassword($newPassword),
            ":ID" => $_SESSION['id'],
            ":LOGIN" => $_SESSION['username']
        ]);

        if($result > 0){
            User::setMsg([Translate::text('success_change_password'), "success"]);
            return true;
        }

        User::setMsg([Translate::text('generic_err_change_password'), "failed"]);
        return false;
    }

    public static function getCharacters(){
        $conn = new Database();

        $result = $conn->select("SELECT id, name, level FROM ".PLAYER_DB.".player WHERE account_id = :ID", [
            ":ID" => $_SESSION['id']
        ]);

        if(count($result) == 0){
            User::setMsg([Translate::text('no_characters_found'), 'failed']);
            return false;
        }

        return $result;
    }
    
    public static function moveCharacter($id){
        $conn = new Database();

        $canMove = $conn->count("SELECT id FROM ".PLAYER_DB.".player WHERE id = :ID AND account_id = :ACCOUNT_ID",[
            ":ID" => (int)$id,
            ":ACCOUNT_ID" => $_SESSION['id']
        ]);

        if($canMove > 0){
            $result = $conn->select("SELECT empire FROM ".PLAYER_DB.".player_index WHERE id = :ID", [
                ":ID" => $_SESSION['id']
            ]);
    
            $empire = $result[0]['empire'];
    
            if($empire === 1){
                $map_index = 1;
                $x = 468779;
                $y = 962107;
            }elseif($empire === 2){
                $map_index = 21;
                $x = 55700;
                $y = 157900;
            }elseif($empire === 3){
                $map_index = 41;
                $x = 969066;
                $y = 278290;
            }
    
            $move = $conn->count("UPDATE ".PLAYER_DB.".player SET x = :X, y = :Y, map_index = :MAP_INDEX WHERE id = :ID",[
                ":X" => $x,
                ":Y" => $y,
                ":MAP_INDEX" => $map_index,
                ":ID" => (int)$id
            ]);
    
            if($move > 0){
                User::setMsg([Translate::text('character_moved'), "success"]);
                return true;
            }
        }else{
            User::setMsg([Translate::text('no_character_moved'), "failed"]);
            return false;
        }

        User::setMsg([Translate::text('character_in_town'), "failed"]);
        return false;
    }

    public static function recoverPassword($user, $mail, $mailContent, $newPassword){
        $conn = new Database();

        $check = $conn->count("SELECT login, email FROM ".ACCOUNT_DB.".account WHERE login = :LOGIN AND email = :MAIL",[
            ":LOGIN" => $user,
            ":MAIL" =>$mail,
        ]);

        if($check > 0){
            //Update account password
            $queryPassword = $conn->count("UPDATE ".ACCOUNT_DB.".account SET password = :NEWPW WHERE login = :LOGIN AND email = :MAIL", [
                ":NEWPW" => hashPassword($newPassword),
                ":LOGIN" => $user,
                ":MAIL" => $mail
            ]);

            if($queryPassword > 0){
                //Send email with new password
                $mail = new Mailer($mail, $user, Translate::text('title_new_password'), $mailContent);

                return $mail;
            }else{
                User::setMsg([Translate::text('fail_to_update_new_pass'), 'failed']);
                return false;
            }

        }else{
            User::setMsg([Translate::text('notfound_user_email'), 'failed']);
            return false;
        }
    }

    public static function sendSocialID($socialID, $mailContent){
        $conn = new Database();

        $userEmail = $conn->select("SELECT email FROM ".ACCOUNT_DB.".account WHERE id = :ID AND login = :LOGIN", [
            ":ID" => $_SESSION['id'],
            ":LOGIN" => $_SESSION['username']
        ]);

        if(count($userEmail) == 0){
            User::setMsg([Translate::text('failed_to_get_user_email'), 'failed']);
            return false;
        }

        $result = $conn->count("UPDATE ".ACCOUNT_DB.".account SET social_id = :SID WHERE id = :ID AND login = :LOGIN",[
            ":SID" => $socialID,
            ":ID" => $_SESSION['id'],
            ":LOGIN" => $_SESSION['username']
        ]);

        if($result > 0){
            $mail = new Mailer($userEmail[0]['email'], $_SESSION['username'], Translate::text('title_social_id'), $mailContent);
            
            return $mail;
        }
        else{
            User::setMsg([Translate::text('fail_to_update_new_pass'), 'failed']);
            return false;
        }
    }   

    public static function sendWarehousePW($warehousePW, $mailContent){
        $conn = new Database();

        $userEmail = $conn->select("SELECT email FROM ".ACCOUNT_DB.".account WHERE id = :ID AND login = :LOGIN", [
            ":ID" => $_SESSION['id'],
            ":LOGIN" => $_SESSION['username']
        ]);

        if(count($userEmail) == 0){
            User::setMsg([Translate::text('failed_to_get_user_email'), 'failed']);
            return false;
        }

        $result = $conn->count("UPDATE ".PLAYER_DB.".safebox SET password = :PW WHERE account_id = :ID", [
            ":PW" => $warehousePW,
            ":ID" => $_SESSION['id']
        ]);

        if($result > 0){
            $mail = new Mailer($userEmail[0]['email'], $_SESSION['username'], Translate::text('title_warehouse_pw'), $mailContent);

            return $mail;
        }else{
            User::setMsg([Translate::text('fail_to_update_new_pass'), 'failed']);
            return false;
        }
    }
}