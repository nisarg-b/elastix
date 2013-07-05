<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.5                                                  |
  | http://www.elastix.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
  +----------------------------------------------------------------------+
  | Cdla. Nueva Kennedy Calle E 222 y 9na. Este                          |
  | Telfs. 2283-268, 2294-440, 2284-356                                  |
  | Guayaquil - Ecuador                                                  |
  | http://www.palosanto.com                                             |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | The Original Code is: Elastix Open Source.                           |
  | The Initial Developer of the Original Code is PaloSanto Solutions    |
  +----------------------------------------------------------------------+
  $Id: paloSantoACL.class.php,v 1.1.1.1 2007/07/06 21:31:55 gcarrillo Exp $ 
  $Id: paloSantoACL.class.php,v 3.0 2012/09/01 21:31:55 Rocio Mera rmera@palosanto.com Exp $ */

$elxPath="/usr/share/elastix";
include_once "$elxPath/libs/paloSantoDB.class.php";

define('PALOACL_MSG_ERROR_1', 'Username or password is empty');
define('PALOACL_MSG_ERROR_2', 'Invalid characters found in username');
define('PALOACL_MSG_ERROR_3', 'Invalid characters found in password hash');

class paloACL {

    var $_DB; // instancia de la clase paloDB
    var $errMsg;

    function paloACL(&$pDB)
    {
        // Se recibe como parámetro una referencia a una conexión paloDB
        if (is_object($pDB)) {
            $this->_DB = $pDB;
            $this->errMsg = $this->_DB->errMsg;
        } else {
            $dsn = (string)$pDB;
            $this->_DB = new paloDB($dsn);

            if (!$this->_DB->connStatus) {
                $this->errMsg = $this->_DB->errMsg;
                // debo llenar alguna variable de error
            } else {
                // debo llenar alguna variable de error
            }
        }
    }

   /**
     * Procedimiento para obtener el listado de los usuarios existentes en los ACL. Los usuarios
     * pertenecen a una entidad
     * Recibe como para parametros el id del usuario y el id de la entidad a la que pertenece,
     * si no se especifica id_user y no se especifica entidad se devuelve todos los usuarios, si se
     * espefica entidad y no id_user todos los usuarios de una entidad, si se especifica id usuario se
     * devuelve solo dicho usuario
     *
     * @param int   $id_user    Si != NULL, indica el ID del usuario a recoger
     *
     * @return array    Listado de usuarios en el siguiente formato, o FALSE en caso de error:
     *  array(
     *      array(id, name, description),
     *      ...
     *  )
     */
	function getUsers($id_user = NULL, $id_organization = NULL, $limit = NULL, $offset = NULL)
    {
        $arr_result = FALSE;
        $where = "";
		$paging = "";
        $arrParams = null;
        if (!is_null($id_user) && !preg_match('/^[[:digit:]]+$/', "$id_user")) {
            $this->errMsg = "User ID is not numeric";
        }elseif (!is_null($id_organization) && !preg_match('/^[[:digit:]]+$/', "$id_organization")) {
            $this->errMsg = _tr("Organization ID must be numeric");
        }else {
			if(!is_null($id_user) && is_null($id_organization)){
				$where = "where u.id=?";
				$arrParams = array($id_user);
			}elseif(is_null($id_user) && !is_null($id_organization)){
				$where = "where g.id_organization=?";
				$arrParams = array($id_organization);
			}elseif(!is_null($id_user) && !is_null($id_organization)){
				$where = "where g.id_organization=? and u.id=?";
				$arrParams = array($id_organization,$id_user);
			}

			if(!is_null($limit) && !is_null($offset)){
				$paging = "limit $limit offset $offset";
			}
            $this->errMsg = "";

            $sPeticionSQL = "SELECT u.id, u.username, u.name, u.md5_password, g.id_organization, u.extension, u.fax_extension, u.id_group FROM acl_user as u JOIN  acl_group as g on u.id_group=g.id $where $paging";
            $arr_result = $this->_DB->fetchTable($sPeticionSQL,false,$arrParams);
            if (!is_array($arr_result)) {
                $arr_result = FALSE;
                $this->errMsg = $this->_DB->errMsg;
            }
        }
        return $arr_result;
    }

	function getUserPicture($id_user){
		$arr_result = FALSE;
		if (!preg_match('/^[[:digit:]]+$/', "$id_user")) {
            $this->errMsg = _tr("User ID must be numeric");
		}else{
			$query="SELECT picture_type,picture_content from acl_user where id=?";
			$arr_result = $this->_DB->getFirstRowQuery($query,true,array($id_user));
			if ($arr_result===false || count($arr_result)==0) {
				$this->errMsg = $this->_DB->errMsg;
			}
		}
        return $arr_result;
	}

	function setUserPicture($id_user,$picture_type,$picture_content){
		$result = FALSE;
		if (!preg_match('/^[[:digit:]]+$/', "$id_user")) {
            $this->errMsg = _tr("User ID must be numeric");
		}else{
			$query="update acl_user set picture_type=?,picture_content=? where id=?";
			$result = $this->_DB->genQuery($query,array($picture_type,$picture_content,$id_user));
		}
        return $result;
	}

	/**
	 * Procedimiento para obtener los datos de la extension usada por el usuario dentro de
	   asterisk
	 * @param int $idUser Id del usuario del que se quiere obtener los datos de su extension
	 * @return array $ext Devuelte un arreglo donde esta el numero de la extension, la tegnologia usada y el nombre del dispositivo usado
	*/
	function getExtUser($id_user){
		$arr_result2=array();
		$pDB2=new paloDB(generarDSNSistema("asteriskuser", "elxpbx"));
		if (!preg_match('/^[[:digit:]]+$/', "$id_user")) {
            $this->errMsg = _tr("User ID must be numeric");
		}else{
			$query="SELECT a.extension, (Select domain from organization o where o.id=g.id_organization) FROM acl_user as a JOIN  acl_group as g on a.id_group=g.id where a.id=?";
			$arr_result = $this->_DB->getFirstRowQuery($query,false,array($id_user));
			if ($arr_result===false){
				$this->errMsg = _tr("Can't get extension user").$this->_DB->errMsg;
			}elseif(count($arr_result)==0) {
				$this->errMsg = _tr("User doesn't have a associated extension");
			}else{
				$query2="SELECT id, exten, organization_domain, tech, dial, voicemail, device FROM extension where exten=? and  organization_domain=?";
				$arr_result2 = $pDB2->getFirstRowQuery($query2,true,array($arr_result[0],$arr_result[1]));
				if (!is_array($arr_result2) || count($arr_result2)==0) {
					$this->errMsg = _tr("Can't get extension user").$pDB2->errMsg;
				}
			}
		}
		return $arr_result2;
	}

	/**
		funcion para obtener la extension del usuario dado su username
	*/
	function getUserExtension($username)
    {
        $extension = null;
        if (is_null($username)) {
            $this->errMsg = _tr("Username is not valid");
        } else {
            $this->errMsg = "";
            $sPeticionSQL = "SELECT extension FROM acl_user WHERE username = ?";
            $result = $this->_DB->getFirstRowQuery($sPeticionSQL, FALSE, array($username));
            if ($result && is_array($result) && count($result)>0) {
               $extension = $result[0];
            }else $this->errMsg = $this->_DB->errMsg;
        }
        return $extension;
    }


    /**
     * Procedimiento para obtener el listado de los usuarios existentes en los ACL. Se
     * especifica un limite y un offset para obtener la data paginada.
     *
     * @param int   $limit    Si != NULL, indica el número de maximo de registros a devolver por consulta
     * @param int   $offset   Si != NULL, indica el principio o desde donde parte la consulta
     *
     * @return array    Listado de usuarios en el siguiente formato, o FALSE en caso de error:
     *  array(
     *      array(id, name, description),
     *      ...
     *  )
     */
    function getUsersPaging($limit = NULL, $offset = NULL, $id_organization = null)
    {
		$arrParams = null;
		$where = "";
		$paging = "";
		$arr_result = FALSE;
        if (!is_null($limit) && !preg_match('/^[[:digit:]]+$/', "$limit")) {
            $this->errMsg = _tr("Limit must be numeric");
            return FALSE;
        }
        if (!is_null($offset) && !preg_match('/^[[:digit:]]+$/', "$offset")) {
            $this->errMsg = _tr("Offset must be numeric");
            return FALSE;
        }
		if(!is_null($limit) && !is_null($offset)){
			$paging = "limit $limit offset $offset";
		}

		if(!is_null($id_organization) && !preg_match('/^[[:digit:]]+$/', "$id_organization")){
            $this->errMsg = _tr("Organization ID must be numeric");
            return FALSE;
		}elseif(!is_null($id_organization)){
			$where = "where g.id_organization=?";
			$arrParams = array($id_organization);
		}

        $this->errMsg = "";
		$sPeticionSQL = "SELECT a.id, a.username, a.name, a.md5_password, g.id_organization, a.extension, a.fax_extension, a.id_group FROM acl_user as a JOIN  acl_group as g on a.id_group=g.id $where $paging" ;

        $arr_result = $this->_DB->fetchTable($sPeticionSQL,false,$arrParams);
        if (!is_array($arr_result)) {
            $arr_result = FALSE;
            $this->errMsg = $this->_DB->errMsg;
        }
        return $arr_result;
    }

    /**
     * Procedimiento para obtener el listado de los grupos existentes en los ACL. Se
     * especifica un limite y un offset para obtener la data paginada.
     *
     * @param int   $limit    Si != NULL, indica el número de maximo de registros a devolver por consulta
     * @param int   $offset   Si != NULL, indica el principio o desde donde parte la consulta
     *
     * @return array    Listado de usuarios en el siguiente formato, o FALSE en caso de error:
     *  array(
     *      array(id, name, description),
     *      ...
     *  )
     */
    function getGroupsPaging($limit = NULL, $offset = NULL, $id_organization = NULL)
    {
		$arrParams = array();
		$where = "";
		$paging = "";
		$arr_result = FALSE;
        if (!is_null($limit) && !preg_match('/^[[:digit:]]+$/', "$limit")) {
            $this->errMsg = _tr("Limit must be numeric");
            return FALSE;
        }
        if (!is_null($offset) && !preg_match('/^[[:digit:]]+$/', "$offset")) {
            $this->errMsg = _tr("Offset must be numeric");
            return FALSE;
        }
		if(!is_null($limit) && !is_null($offset)){
			$paging = "limit $limit offset $offset";
		}


		if(!is_null($id_organization) && !preg_match('/^[[:digit:]]+$/', "$id_organization")){
            $this->errMsg = _tr("Organization ID must be numeric");
            return FALSE;
		}elseif(!is_null($id_organization)){
			$where = "where id_organization=?";
			$arrParams = array($id_organization);
		}
		
        $this->errMsg = "";
        $sPeticionSQL = "SELECT id, name, description, id_organization FROM acl_group $where $paging";

        $arr_result = $this->_DB->fetchTable($sPeticionSQL,false,$arrParams);
        if (!is_array($arr_result)) {
            $arr_result = FALSE;
            $this->errMsg = $this->_DB->errMsg;
        }
        return $arr_result;
    }

    /**
     * Procedimiento para obtener la cantidad de usuarios existentes en los ACL.
     *
     * @return int    Cantidad de usuarios existentes, o NULL en caso de error:
     */
    function getNumUsers($id_organization = NULL)
    {
        $this->errMsg = "";
		$arrParams = null;
		$where = "";

		if(!is_null($id_organization) && !preg_match('/^[[:digit:]]+$/', "$id_organization")){
            $this->errMsg = _tr("Organization ID must be numeric");
            return FALSE;
		}elseif(!is_null($id_organization)){
			$where = "where g.id_organization=?";
			$arrParams = array($id_organization);
		}

        $sPeticionSQL = "SELECT count(*) FROM acl_user as a JOIN  acl_group as g on a.id_group=g.id $where";

        $data = $this->_DB->getFirstRowQuery($sPeticionSQL,false,$arrParams);
        if (!is_array($data) || count($data) <= 0) {
            $this->errMsg = $this->_DB->errMsg;
            return FALSE;
        }
        return $data[0];
    }

    /**
     * Procedimiento para obtener la cantidad de grupos existentes en los ACL.
     *
     * @return int    Cantidad de usuarios existentes, o NULL en caso de error:
     */
    function getNumGroups($id_organization = NULL)
    {
        $this->errMsg = "";
		$arrParams = null;
		$where = "";

		if(!is_null($id_organization) && !preg_match('/^[[:digit:]]+$/', "$id_organization")){
            $this->errMsg = _tr("Organization ID must be numeric");
            return FALSE;
		}elseif(!is_null($id_organization)){
			$where = "where id_organization=?";
			$arrParams = array($id_organization);
		}

        $sPeticionSQL = "SELECT count(*) cnt FROM acl_group $where";

        $data = $this->_DB->getFirstRowQuery($sPeticionSQL,true,$arrParams);
        if (!is_array($data) || count($data) <= 0) {
            $this->errMsg = $this->_DB->errMsg;
            return false;
        }
        return $data['cnt'];
    }

    /**
     * Procedimiento para crear un nuevo usuario con hash MD5 de la clave ya proporcionada.
     *
     * @param string    $username       Login del usuario a crear
     * @param string    $name    Descripción del usuario a crear
     * @param string    $md5_password   Hash MD5 de la clave a asignar (32 dígitos y letras min a-f)
     *
     * @return bool     VERDADERO si el usuario se crea correctamente, FALSO en error
     */
	// 1) debo validar que el grupo exista y que dicho grupo pertenezca a la organizacion
	// 2) no puede ser un grupo que pertenezca a la organization con id 1, ya que esta es solo una organizacion
	//    de administracion y su unico usuario es el superadmin
    function createUser($username, $name, $md5_password, $id_group, $extension,  $fax_extension, $idOrganization)
    {
        $bExito = FALSE;
        if ($username == "") {
            $this->errMsg = _tr("Username can't be empty");
        } elseif(!preg_match("/^[a-z0-9]+([\._\-]?[a-z0-9]+[_\-]?)*@[a-z0-9]+([\._\-]?[a-z0-9]+)*(\.[a-z0-9]{2,4})+$/", $username)){
            $this->errMsg = _tr("Username is not valid");
        }else{
            if ( !$name ) $name = $username;
            // Verificar que el nombre de usuario no existe previamente
            $id_user = $this->getIdUser($username);
            if ($id_user !== FALSE) {
                $this->errMsg = _tr("Username already exists");
            } elseif ($this->errMsg == "") {
            //El id_group no puede ser el grupo del superadmin, superadmin_group=1
                if(!preg_match("/^[[:digit:]]+$/","$id_group") || $id_group=="1"){ // 1)
                    $this->errMsg = _tr("Grout ID is not valid");
                    return false;
                }

            //El id_organization no puede ser 1
                if(!preg_match("/^[[:digit:]]+$/","$idOrganization") || $idOrganization=="1"){ // 2)
                    $this->errMsg = _tr("Organization ID is not valid");
                    return false;
                }

            //validar que el grupo exista y que pertenezca a la misma organization que el usuario
                $arrGroup=$this->getGroups($id_group, $idOrganization);
                if($arrGroup==false){ // 2)
                    $this->errMsg = _tr("Group dosen't exist");
                    return false;
                }

                $sPeticionSQL = "INSERT into acl_user (username,name,md5_password,id_group,extension,fax_extension) VALUES (?,?,?,?,?,?)";
                $arrParam = array($username,$name,$md5_password,$id_group,$extension, $fax_extension);
                if ($this->_DB->genQuery($sPeticionSQL,$arrParam)) {
                    $bExito = TRUE;
                } else {
                    $this->errMsg = $this->_DB->errMsg;
                }
            }
        }
        return $bExito;
    }

    /**
     * Procedimiento para modificar al usuario con el ID de usuario especificado, para darle una nueva extension, fax extension y description
     *
     * @param int       $id_user        Indica el ID del usuario a modificar
     * @param string    $name           nombre descriptivo del usuario
     * @param string    $extension      extension telefonica del usuario
     * @param string    $fax_extension  extensión de fax del usuario
     *
     * @return bool VERDADERO si se modifico correctamente el usuario, FALSO si ocurre un error.
     */
    function updateUser($id_user, $name, $extension, $fax_extension)
    {
        $bExito = FALSE;

		if (!preg_match("/^[[:digit:]]+$/", "$id_user")) {
            $this->errMsg = _tr("User ID must be numeric");
        } else {

			// Verificar que el usuario indicado existe
			$tuplaUser = $this->getUsers($id_user);
			if (!is_array($tuplaUser)) {
				$this->errMsg =_tr("On having checked user's existence - ").$this->errMsg;
			} else if (count($tuplaUser) == 0) {
				$this->errMsg = _tr("User doesn't exist");
			} else {
				$bContinuar = TRUE;
			}

			if ( !$name ) $name = $tuplaUser[0][1];

			if ($bContinuar) {
				// Proseguir con la modificación del usuario
				$sPeticionSQL = "UPDATE acl_user SET name = ?, extension  = ?, fax_extension  = ? WHERE id = ?";
				$arrParam = array($name,$extension,$fax_extension,$id_user);
				if ($this->_DB->genQuery($sPeticionSQL,$arrParam)) {
					$bExito = TRUE;
				} else {
					$this->errMsg = $this->_DB->errMsg;
				}
			}
		}
        return $bExito;
    }

    /**
     * Procedimiento para cambiar la clave de un usuario, dado su ID de usuario.
     *
     * @param int       $id_user        ID del usuario para el que se cambia la clave
     * @param string    $md5_password   Nuevo hash MD5 a asignar al usuario
     *
     * @return bool VERDADERO si se modifica correctamente el usuario, FALSO si ocurre un error.
     */
    function changePassword($id_user, $md5_password)
    {
        $bExito = FALSE;
        if (!preg_match("/^[[:digit:]]+$/", "$id_user")) {
            $this->errMsg = _tr("User ID must be numeric");
        } else if (!preg_match("/^[[:digit:]a-f]{32}$/", $md5_password)) {
            $this->errMsg = _tr("Password is not a valid MD5 hash");
        } else {
             if ($this->errMsg == "") {
				$sPeticionSQL = "UPDATE acl_user SET md5_password = ? WHERE id = ?";
				if ($this->_DB->genQuery($sPeticionSQL,array($md5_password,$id_user))) {
					$bExito = TRUE;
				} else {
					$this->errMsg = $this->_DB->errMsg;
				}
			}
        }

        return $bExito;
    }
    
    /**
     * Procedimiento para borrar un usuario ACL, dado su ID numérico de usuario
     *
     * @param int   $id_user    ID del usuario que debe eliminarse
     *
     * @return bool VERDADERO si el usuario puede borrarse correctamente
     */
    function deleteUser($id_user)
    {
        $bExito = FALSE;
        if (!preg_match('/^[[:digit:]]+$/', "$id_user") || $id_user=="1") {
            $this->errMsg = _tr("User ID is not valid");
        } else {
            $this->errMsg = "";
            $query = "DELETE FROM acl_user WHERE id=?";
            $bExito = $this->_DB->genQuery($sPeticionSQL,array($id_user));
            if (!$bExito) {
                $this->errMsg = $this->_DB->errMsg;
            }
		}
        return $bExito;
    }

    /**
     * Procedimiento para averiguar el ID de un usuario, dado su login (nombre@dominio).
     *
     * @param string    $login    Login del usuario para buscar ID
     *
     * @return  mixed   Valor entero del ID de usuario, o FALSE en caso de error o si el usuario no existe
     */
    function getIdUser($username)
    {
        $idUser = FALSE;
        $this->errMsg = '';
        $sPeticionSQL = "SELECT id FROM acl_user WHERE username = ?";
		
        $result = $this->_DB->getFirstRowQuery($sPeticionSQL,false,array($username));
		if (is_array($result) && count($result)>0) {
            $idUser = $result[0];
        }else
			$this->errMsg = $this->_DB->errMsg;
        return $idUser;
    }

    /**
     * Procedimiento para obtener el listado de los grupos existentes en los ACL.
     * cada organizacion tiene sus propios grupos.
     * Se recibe como parametros el id del grupo y el id de la organizacion a la que pertenece el grupo
     *
     * @param int   $id_group    Si != NULL, indica el ID del grupos a recoger
     * @param int   $id_organization   Si != NULL, indica el ID de la organization a la que pertenece el grupo
     *
     * @return array    Listado de grupos en el siguiente formato, o FALSE en caso de error:
     *  array(
     *      array(id, name, description),
     *      ...
     *  )
     */
    function getGroups($id_group = NULL, $id_organization = NULL)
    {
        $arr_result = FALSE;
        $where = "";
        $arrParams = null;
        if (!is_null($id_group) && !preg_match('/^[[:digit:]]+$/', "$id_group")) {
            $this->errMsg = _tr("Group ID must be numeric");
        }else if(!is_null($id_organization) && !preg_match('/^[[:digit:]]+$/', "$id_organization")) {
            $this->errMsg = _tr("Organization ID must be numeric");
        }else {
            if(!is_null($id_group) || !is_null($id_organization)){
                $where = "where ";
                $arrParams = array();
                if(!is_null($id_group)){
                    $where .= "id=?";
                    $arrParams[] = $id_group;
                }
                if(!is_null($id_group) && !is_null($id_organization))
                    $where .= " and ";
                if(!is_null($id_organization)){
                    $where .= "id_organization=?";
                    $arrParams[] = $id_organization;
                }
            }
            $this->errMsg = "";
            $sPeticionSQL = "SELECT id, name, description, id_organization FROM acl_group $where;";
            $arr_result = $this->_DB->fetchTable($sPeticionSQL,false,$arrParams);
            if (!is_array($arr_result)) {
                $arr_result = FALSE;
                $this->errMsg = $this->_DB->errMsg;
            }
        }
        return $arr_result;
    }

    /**
     * Procedimiento para construir un arreglo que describe el grupo al cual
     * pertenece un usuario identificado por un ID. El arreglo devuelto tiene el siguiente
     * formato:
     *  array(
     *      nombre_grupo_1  =>  id_grupo_1,
     *  )
     *
     * @param int   $id_user    ID del usuario para el cual se pide la pertenencia
     *
     * @return mixed    Arreglo que describe la pertenencia, o NULL en caso de error.
     */
    function getMembership($id_user)
    {
        $arr_resultado = NULL;
        if (!is_null($id_user) && !preg_match('/^[[:digit:]]+$/', "$id_user")) {
            $this->errMsg = _tr("User ID must be numeric");
        } else {
            $this->errMsg = "";
            $sPeticionSQL =
                "SELECT g.id, g.name ".
                "FROM acl_group as g, acl_user as u ".
                "WHERE u.id_group = g.id AND u.id = ?";
            $result = $this->_DB->getFirstRowQuery($sPeticionSQL, FALSE, array($id_user));
            if($result==false){
                $this->errMsg = ($result===false)?$this->_DB->errMsg:"User doen't belong to any group";
            }else{
                $arr_resultado[$result[1]] = $result[0];
            }
        }
        return $arr_resultado;
    }


    /**
     * Procedimiento para averiguar el ID de un grupo, dado su nombre y la entidad del grupo.
     *
     * @param string    $sNombreUser    Login del usuario para buscar ID
     *
     * @return  mixed   Valor entero del ID de usuario, o FALSE en caso de error o si el usuario no existe
     */
    function getIdGroup($sNombreGroup,$id_organization)
    {
        $idGroup = FALSE;

        if(!preg_match('/^[[:digit:]]+$/', "$id_organization")) {
            $this->errMsg = _tr("Organization ID must be numeric");
            return false;
        }

        $arrParams = array($sNombreGroup, $id_organization);

        $this->errMsg = '';
        $sPeticionSQL = "SELECT id FROM acl_group WHERE name = ? and id_organization = ?";
        $result = $this->_DB->getFirstRowQuery($sPeticionSQL, FALSE, $arrParams);
        if (is_array($result) && count($result)>0) {
            $idGroup = $result[0];
        }else $this->errMsg = $this->_DB->errMsg;
        return $idGroup;
    }
    
    /**
     * Procedimiento para asegurar que un usuario identificado por su ID pertenezca al grupo
     * identificado también por su ID. Se verifica primero que tanto el usuario como el grupo
     * existen en las tablas ACL.
     *
     * @param int   $id_user    ID del usuario que se desea agregar al grupo
     * @param int   $id_group   ID del grupo al cual se desea agregar al usuario
     *
     * @return bool VERDADERO si se puede agregar el usuario al grupo, o si ya pertenecía al grupo
     */
    function addToGroup($id_user, $id_group)
    {
        $bExito = FALSE;
        if (is_null($id_user) || is_null($id_group)) {
            $this->errMsg = _tr("User ID and Group ID can't be empty");
        }elseif(!preg_match('/^[[:digit:]]+$/', "$id_user")) {
            $this->errMsg = _tr("User ID must be numeric");
        }elseif( !preg_match('/^[[:digit:]]+$/', "$id_group") || $id_group=="1" ) {
            $this->errMsg = _tr("Group ID is not valid");
		}elseif (is_array($listaUser = $this->getUsers($id_user)) &&
            is_array($listaGrupo = $this->getGroups($id_group))) {

            if (count($listaUser) == 0) {
                $this->errMsg = _tr("User doesn't exist");
            } else if (count($listaGrupo) == 0) {
                $this->errMsg = _tr("Group doesn't exist");
            } elseif($listaGrupo[0][3]=="1") {//valido que el grupo no pertenezca a la organizacion 1
				$this->errMsg = _tr("Group ID is not valid");
			} else{
                // Verificar existencia de la combinación usuario-grupo
                $sPeticionSQL = "SELECT id FROM acl_user WHERE id = ? AND id_group = ?";
                $arrusuario = $this->_DB->fetchTable($sPeticionSQL,false,array($id_user, $id_group));
                if (!is_array($arrusuario)) {
                    // Ocurre un error de base de datos
                    $this->errMsg = $this->_DB->errMsg;
                } else if (is_array($arrusuario) && count($arrusuario) > 0) {
                    // El usuario ya pertecene al grupo el grupo - no se hace nada
                    $bExito = TRUE;
                } else {
                    // El usuario no pertenece al grupo - se debe de agregar
					// antes de agregarlo se debe verificar que el grupo al que se
					// lo quiere agregar al usuario pertenezca a la misma organizacion
					// a la que ya pertence el usuario
					$query="select count(u.id) from acl_user as u join acl_group as g on g.id=u.id_group and u.id=? and g.id_organization=?";
					$bellow=$this->_DB->getFirstRowQuery($query,false,array($id_user,$listaGrupo[0][3]));
					if($bellow[0]==1){
						$sPeticionSQL = "Update acl_user set id_group=? where id=?";
						$bExito = $this->_DB->genQuery($sPeticionSQL,array($id_group,$id_user));
						if (!$bExito) {
							// Ocurre un error de base de datos
							$this->errMsg = $this->_DB->errMsg;
						}
					}else{
						$this->errMsg = _tr("Invalid new Group");
					}
                }
            }
        }
        return $bExito;
    }

    /**
      *  Procedimiento para setear una propiedad de un usuario, dado el id del usuario,
      *  el nombre de la propiedad y el valor de la propiedad
      *  Si la propiedad ya existe actualiza el valor, caso contrario crea el nuevo registro
      *  @param integer $id del usuario al que se le quiere setear la propiedad
      *  @param string $key nombre de la propiedad
      *  @param string $value valor que tomarà la propiedad
      *  @return boolean verdadera si se ejecuta con existo la accion, falso caso contrario
    */
    function setUserProp($id,$key,$value,$category=""){
        $bQuery = "select 1 from user_properties where id_user=? and property=?";
        $bResult=$this->_DB->getFirstRowQuery($bQuery,false, array($id,$key));
        if($bResult===false){
            $this->errMsg = $this->_DB->errMsg;
            return false;
        }else{
            if(count($bResult)==0){
                $query="INSERT INTO user_properties values (?,?,?,?)";
                $arrParams=array($id,$key,$value,$category);
            }else{
                if($bResult[0]=="1"){
                $query="UPDATE user_properties SET value=? where id_user=? and property=?";
                $arrParams=array($value,$id,$key);}
            }
            $result=$this->_DB->genQuery($query, $arrParams);
            if($result==false){
                $this->errMsg = $this->_DB->errMsg;
                return false;
            }else
                return true;
        }
    }

	function getUserProp($id,$key){
        $bQuery = "select value from user_properties where id_user=? and property=?";
        $bResult=$this->_DB->getFirstRowQuery($bQuery,false, array($id,$key));
        if($bResult==false){
            $this->errMsg = $this->_DB->errMsg;
            return false;
        }else{
			return $bResult[0];
        }
    }

	//funcion usada para obtener parametros del usuario como username, fax_extesion, extension, name
	//recibe como parametros el id del usuario y el nombre del parametro que desea consultar
	function getUserParameter($id_user,$key){
		$bQuery = "select id, $key from acl_user where id_user=?";
        $bResult=$this->_DB->getFirstRowQuery($bQuery,true, array($id));
        if($bResult==false){
            $this->errMsg = $this->_DB->errMsg;
            return false;
        }else{
			return $bResult;
        }
	}



    function isUserAuthorizedById($id_user, $resource_name)
    {
$sPeticionSQL = <<<INFO_AUTH_MODULO
    SELECT count(ogr.id_resource) From organization_resource as ogr
        JOIN group_resource_actions as gr on ogr.id=gr.id_org_resource
        WHERE gr.id_action='access' AND ogr.id_resource=?
            AND gr.id_group=(Select u.id_group from acl_user as u where u.id=?);
INFO_AUTH_MODULO;
        $result=$this->_DB->fetchTable($sPeticionSQL,false,array($resource_name,$id_user));
        if(is_array($result) && count($result)>0){
            return true;
        }else
            return false;
    }

    function isUserAuthorized($username, $resource_name)
    {    
        if($id_user = $this->getIdUser($username)) {
            $resultado = $this->isUserAuthorizedById($id_user, $action_name, $resource_name);
        } else {
            $resultado = false;
        }
        return $resultado;
    }

    // Procedimiento para buscar la autenticación de un usuario en la tabla de ACLs.
    // Devuelve VERDADERO si el usuario existe y tiene el password MD5 indicado,
    // FALSE si no lo tiene, o en caso de error
    function authenticateUser($user, $pass)
    {
        $user = trim($user);
        $pass = trim($pass);
        //$pass = md5($pass);
        if ($this->_DB->connStatus) {
            return FALSE;
        } else {
           $this->errMsg = "";
            if($user == "" or $pass == "") {
                $this->errMsg = PALOACL_MSG_ERROR_1;
                return FALSE;
            }else{
				$idUser =$this->getIdUser($user);
				if($idUser===false){
					$this->errMsg = _tr("User doesn't exist");
					return FALSE;
				}

				if (!preg_match("/^[[:alnum:]]{32}$/", $pass)) {
					$this->errMsg = PALOACL_MSG_ERROR_3;
					return FALSE;
				//validamos el usuario
				} else if($this->userBellowMainOrganization($idUser)){
					if (!preg_match("/^[[:alnum:]\.\\-_]+$/", $user)) {
						$this->errMsg = PALOACL_MSG_ERROR_2;
						return FALSE;
					}
				}else {
					if(!preg_match("/^[a-z0-9]+([\._\-]?[a-z0-9]+[_\-]?)*@[a-z0-9]+([\._\-]?[a-z0-9]+)*(\.[a-z0-9]{2,4})+$/", $user)) {
						$this->errMsg = PALOACL_MSG_ERROR_2;
						return FALSE;
					}
				}
			}

			//comprobamos que el usuario exista, que la clave de login del usuario sea la correcta y que se encuentre relacionado
			//con una organizacion y que esta organizacion este activa en el sistema
            $sql = "SELECT id FROM acl_user WHERE username = ? AND md5_password = ?";
            $arr = $this->_DB->getFirstRowQuery($sql,false,array($user,$pass));
            if (is_array($arr)) {
                if(count($arr) > 0){
					$idOrganization = $this->getIdOrganizationUser($arr[0]);
					if($idOrganization==false)
						return false;
					else{
                        $query="Select 1 from organization where id=? and state=?";
                        $res = $this->_DB->getFirstRowQuery($query,false,array($idOrganization,"active"));
                        if($res==false){
                            $this->errMsg=_tr("User is part a no-active Organization in the System");
                            return FALSE;
                        }else
                            return true;
                    }
				 }else{
					return FALSE;
				 }
            } else {
                $this->errMsg = $this->_DB->errMsg;
                return FALSE;
            }
        }
    }

	//procedimiento para saber si el usuario pertenece al superentidad
	//esa es la entidad principal que es dueña del servidor y a la que pertence superadmin
	//se identifica porque el id de la entidad es 1
	function userBellowMainOrganization($idUser)
	{
		//avereriguamos a que grupo pertenece el usuario
		$id_Organization=$this->getIdOrganizationUser($idUser);
		//error
		if($id_Organization!==false){
			if($id_Organization == "1")
				return true;
		}
		return false;
	}

	function userBellowOrganization($idUser,$idOrganization)
	{
		//avereriguamos a que grupo pertenece el usuario
		$id_Organization=$this->getIdOrganizationUser($idUser);
		//error
		if($id_Organization!==false){
			if($id_Organization == $idOrganization)
				return true;
		}
		return false;
	}

	//funcion que devuelve el id de la organizacion a la que pertenece un usuario dado el id del usuario
	function getIdOrganizationUser($idUser)
	{
        $id_Organization = false;
        if (!preg_match('/^[[:digit:]]+$/', "$idUser")) {
            $this->errMsg = _tr("User ID is not valid");
            return false;
        }
        $sql="Select g.id_organization from acl_group as g join acl_user as u on u.id_group=g.id where u.id=?";
        $result = $this->_DB->getFirstRowQuery($sql,true,array($idUser));
        if (is_array($result)) {
            if(count($result)>0)
                $id_Organization = $result["id_organization"];
            else
                $this->errMsg = _tr("User doesn't exist");
        }else 
            $this->errMsg = $this->_DB->errMsg;
		return $id_Organization;
    }

    //funcion que devuelve el id de la organizacion a la que pertenece un usuario dado su username
    function getIdOrganizationUserByName($username)
    {
        $idUser=$this->getIdUser($username);
        $id_Organization=$this->getIdOrganizationUser($idUser);
        return $id_Organization;
    }

    /**
     * Procedimiento para saber si un usuario (login) pertenece al grupo administrador
     *
     * @param string   $username  Username del usuario
     *
     * @return boolean true or false
     */
    function isUserAdministratorGroup($username)
    {
        $is=false;
        $idUser = $this->getIdUser($username);
        if($idUser){
            $arrGroup = $this->getMembership($idUser);
            $is = array_key_exists('administrator',$arrGroup);
        }
        return $is;
    }

     /**
     * Procedimiento para saber si un usuario (login) es super administrador
     *
     * @param string   $username  Username del usuario
     *
     * @return boolean true or false
     */
    function isUserSuperAdmin($username)
    {
        $is=false;
        $idUser = $this->getIdUser($username);
        if($idUser){
            $arrGroup = $this->getMembership($idUser);
            $is = array_search('1', $arrGroup);
            if($username=="admin" && $is!==false){
                return true;
			}
        }
        return false;
    }


    /**
     * Procedimiento para crear un nuevo grupo
     *
     * @param string    $group       nombre del grupo a crear
     * @param string    $description    Descripción del grupo a crear
        * @param string    $id_organization    id de la organization a la que pertenece el grupo a crear
        *
        * @return bool     VERDADERO si el grupo se crea correctamente, FALSO en error
        */
    function createGroup($group, $description, $id_organization)
    {
        $bExito = FALSE;
        //validamos que el id de la organizacion sea numerico
        //no se le pueden crear nuevos grupos a la organizacion 1, ya que esta es solo de administracion
        if (!preg_match("/^[[:digit:]]+$/", "$id_organization") || $id_organization==1){
            $this->errMsg = _tr("Organization ID is not valid");
        }else if ($group == "") {
            $this->errMsg = _tr("Group can't be empty");
        } else {
            if ( !$description ) $description = $group;
            // Verificar que exista la organizacion
            $query="select id from organization where id=?";
            $result=$this->_DB->getFirstRowQuery($query,false,array($id_organization));
            if($result===false){
                $this->errMsg = $this->_DB->errMsg;
            }elseif(count($result)==0){
                $this->errMsg = _tr("Organization doesn't exist");
            }else{
                // Verificar que el nombre de Grupo no existe previamente
                $id_group = $this->getIdGroup($group, $id_organization);
                if ($id_group !== FALSE) {
                    $this->errMsg = _tr("Group already exists");
                } elseif ($this->errMsg == "") {
                    $sPeticionSQL = "INSERT INTO acl_group (description,name,id_organization) values(?,?,?);";
                    if ($this->_DB->genQuery($sPeticionSQL,array($description,$group, $id_organization))) {
                        $bExito = TRUE;
                    } else {
                        $this->errMsg = $this->_DB->errMsg;
                    }
                }
            }
        }

        return $bExito;
    }

    /**
     * Procedimiento para modificar al grupo con el ID de grupo especificado, para
     * darle un nuevo nombre y descripción.
     *
     * @param int       $id_group        Indica el ID del grupo a modificar
     * @param string    $group           Grupo a modificar
     * @param string    $description     Descripción del grupo a modificar
     *
     * @return bool VERDADERO si se ha modificado correctamente el grupo, FALSO si ocurre un error.
     */
    function updateGroup($id_group, $group, $description)
    {
        $bExito = FALSE;
        if ($group == "") {
            $this->errMsg = _tr("Group can't be empty");
        } else if (!preg_match("/^[[:digit:]]+$/", "$id_group")) {
            $this->errMsg = _tr("Group ID must be numeric");
        } else {
            if ( !$description ) $description = $group;

            // Verificar que el grupo indicado existe
            $tuplaGroup = $this->getGroups($id_group);
            if (!is_array($tuplaGroup)) {
                $this->errMsg = _tr("On having checked group's existence - ").$this->errMsg;
            } else if (count($tuplaGroup) == 0) {
                $this->errMsg = _tr("Group doesn't exist");
            } else {
                $bContinuar = TRUE;

                // Si el nuevo group es distinto al anterior, se verifica si el nuevo
                // group colisiona con uno ya existente
                if ($tuplaGroup[0][1] != $group) {
                    $id_group_conflicto = $this->getIdGroup($group);
                    if ($id_group_conflicto !== FALSE) {
                        $this->errMsg = _tr("Group already exists");
                        $bContinuar = FALSE;
                    } elseif ($this->errMsg != "") {
                        $bContinuar = FALSE;
                    }
                }

                if ($bContinuar) {
                    // Proseguir con la modificación del grupo
					// Proseguir con la modificación del grupo
                    $sPeticionSQL = "UPDATE acl_group set description=? where id=?";
                    if ($this->_DB->genQuery($sPeticionSQL,array($description,$id_group))) {
                        $bExito = TRUE;
                    } else {
                        $this->errMsg = $this->_DB->errMsg;
                    }
                }
            }
        }
        return $bExito;
    }

    /**
     * Procedimiento para borrar un grupo ACL, dado su ID numérico de grupo
     *
     * @param int   $id_group    ID del grupo que debe eliminarse
     *
     * @return bool VERDADERO si el grupo puede borrarse correctamente
     */
    function deleteGroup($id_group)
    {
        if (!preg_match('/^[[:digit:]]+$/', "$id_group") ) {
            $this->errMsg = _tr("Group ID must be numeric");
            return false;
        } else {
            //no se pueden borrar los grupos por default de elasstix
            $arrGroup=$this->getGroups($id_group);
            if(is_array($arrGroup) && count($arrGroup)>0){
                if($arrGroup[0][3]=="1"){
                    $this->errMsg = _tr("Invalid Group");
                    return FALSE;
                }
            }else{
                $this->errMsg = _tr("Group doesn't exist").$this->errMsg;
                return FALSE;
            }

            $this->errMsg = "";
            $query = "DELETE FROM acl_group WHERE id = ?";
            //no deben haber usuarios ertenecientes al grupo para que este puede ser borrado
            if(!($this->HaveUsersTheGroup($id_group))){
                $bExito = $this->_DB->genQuery($query, array($id_group));
                if (!$bExito) {
                    $this->errMsg = $this->_DB->errMsg;
                }
            }else{
                $this->errMsg = _tr("You can not delete this group. You must delete all users belong this group before to delete the group");
                return FALSE;
            }
        }
        return $bExito;
    }

    function HaveUsersTheGroup($id_group)
    {
        $Haveusers = TRUE;
        $sPeticionSQL = "SELECT count(id) FROM acl_user WHERE id_group = ?";
        $result = $this->_DB->getFirstRowQuery($sPeticionSQL, FALSE,array($id_group));
        if(is_array($result)) {
            $numUsers = $result[0];
            if($numUsers==0)
                $Haveusers = FALSE;
        }else{
            $this->errMsg = $this->_DB->errMsg;
        }
        return $Haveusers;
    }

     /**
     * Procedimiento para obtener el listado de los recursos existentes en los ACL. Si
     * se especifica un ID numérico de recurso, el listado contendrá únicamente al recurso
     * indicado. De otro modo, se listarán todos los recursos.
     *
     * @param int   $id_rsrc    Si != NULL, indica el ID del recurso a recoger
     *
     * @return array    Listado de recursos en el siguiente formato, o FALSE en caso de error:
     *  array(
     *      array(id, name, description),
     *      ...
     *  )
     */
    function getResources($id_rsrc = NULL)
    {
        $arr_result = FALSE;
		$where = "";
		$arrParams = null;
		if(!is_null($id_rsrc)){
			$where = " and id = ?";
			$arrParams = array($id_rsrc);
		}
		$this->errMsg = "";
		$sPeticionSQL = "SELECT id, description FROM acl_resource WHERE Type!='' $where";
		$arr_result = $this->_DB->fetchTable($sPeticionSQL, false,$arrParams);
		if (!is_array($arr_result)) {
			$arr_result = FALSE;
			$this->errMsg = $this->_DB->errMsg;
		}
        return $arr_result;
    }

	/**
     * Procedimiento para obtener el listado de los recursos existentes en los ACL dado
     * el id de una organizacion. Si se especifica un el nombre del recurso, el listado contendrá únicamente
     * al recurso indicado. De otro modo, se listarán todos los recursos a los que tenga acceso dicha organizacion.
     *
     * @param int   $id_rsrc    Si != NULL, indica el ID del recurso a recoger
     *
     * @return array    Listado de recursos en el siguiente formato, o FALSE en caso de error:
     *  array(
     *      array(id, name, description),
     *      ...
     *  )
     */
	function getResourcesByOrg($id_Organization, $filter_resource = NULL)
    {
        $arr_result = FALSE;
		$where = "";
        if (!preg_match('/^[[:digit:]]+$/', "$id_Organization")) {
            $this->errMsg = _tr("Organization ID must be numeric");
        } else {
			$arrParams = array($id_Organization);
			if(isset($filter_resource)){
				if(!is_array($filter_resource)){
					$where = " and description LIKE ? ";
					$arrParams[] = "%$filter_resource%";
				}else{
					$where = " and (";
					$i=1;
					foreach($filter_resource as $key=>$value){
						if($i==count($filter_resource)){
							$where .= "description LIKE ? ) ";
							$arrParams[] = "%$value%";
						}
						else{
							$where .= "description = ? or ";
							$arrParams[] = $value;
						}
						$i++;
					}
				}
			}
            $this->errMsg = "";
            $sPeticionSQL = "SELECT ar.id, ar.description FROM acl_resource ar JOIN organization_resource ogr on ar.id=ogr.id_resource WHERE Type!='' and id_organization=? $where";
            $arr_result = $this->_DB->fetchTable($sPeticionSQL, true,$arrParams);
            if (!is_array($arr_result)) {
                $arr_result = FALSE;
                $this->errMsg = $this->_DB->errMsg;
            }
        }
        return $arr_result;
    }


    /**
     * Procedimiento para crear un recurso bajo el nombre descrito, con una descripción opcional.
     * Si un recurso con el nombre indicado ya existe, se reemplaza la descripción.
     *
     * @param string    $name           Nombre del grupo a crear
     * @param string    $description    Descripción del grupo a crear, opcional
     *
     * @return bool     VERDADERO si el grupo ya existe o fue creado/actualizado correctamente
     */
    function createResource($name, $description, $id_parent, $type='module', $link='', $order=-1)
    {
        $bExito = FALSE;
        $this->errMsg = "";
        if ($name == "") {
            $this->errMsg = _tr("Resource Name can't be empty");
        } else {
            if ($description == '') $description = $name;

            // Verificar si el recurso ya existe
			$sPeticionSQL = "SELECT id FROM acl_resource WHERE id = ? AND description = ? AND IdParent = ?";

            $tupla = $this->_DB->getFirstRowQuery($sPeticionSQL,false,array($name,$description,$id_parent));
            if (!is_array($tupla)) {
                // Ocurre error de DB en consulta
                $this->errMsg = $this->_DB->errMsg;
            } else if (is_array($tupla) && count($tupla) > 0) {
				$bExito = FALSE;
                $this->errMsg = _tr("Menu already exists");
			}else{
				if($order!=-1){
					$sPeticionSQL = "Insert INTO acl_resource (id,description,Type,Link,IdParent,order_no) values(?,?,?,?,?,?)";
					$arrParams=array($name, $description,$type,$link,$id_parent,$order);
                }
                else{
					$sPeticionSQL = "Insert INTO acl_resource (id,description,Type,Link,IdParent) values(?,?,?,?,?)";
					$arrParams=array($name, $description,$type,$link,$id_parent);
                }
				if ($this->_DB->genQuery($sPeticionSQL,$arrParams)) {
                    $bExito = TRUE;
                } else {
                    $this->errMsg = $this->_DB->errMsg;
                }
			}
        }
        return $bExito;
    }

    //************************************************************************************************************************
    //************************************************************************************************************************
    function getNumResources($filter_resource = NULL)
    {
		$where = "";
		$arrParam=array();
		if(isset($filter_resource)){
			if(!is_array($filter_resource)){
				$where = " and description LIKE ? ";
				$arrParam = array("%$filter_resource%");
			}else{
				$where = " and (";
				$i=1;
				$arrParam = array();
				foreach($filter_resource as $key=>$value){
					if($i==count($filter_resource)){
						$where .= "description LIKE ? ) ";
						$arrParam[] = "%$value%";
					}
					else{
						$where .= "description = ? or ";
						$arrParam[] = $value;
					}
					$i++;
				}
			}
		}
		$query = "SELECT count(id) FROM acl_resource WHERE Type!='' $where";
        $result = $this->_DB->getFirstRowQuery($query, FALSE, $arrParam);

        if( $result == false )
        {
            $this->errMsg = $this->_DB->errMsg;
            return 0;
        }
        return $result[0];
    }

    function getListResources($limit, $offset, $filter_resource=null)
    {
		$where = "";
		$arrParam=array();

        if(isset($filter_resource)){
			if(!is_array($filter_resource)){
				$where = " and description LIKE ? ";
				$arrParam = array("%$filter_resource%");
			}else{
				$where = " and (";
				$i=1;
				$arrParam = array();
				foreach($filter_resource as $key=>$value){
					if($i==count($filter_resource)){
						$where .= "description LIKE ? ) ";
						$arrParam[] = "%$value%";
					}
					else{
						$where .= "description = ? or ";
						$arrParam[] = $value;
					}
					$i++;
				}
			}
		}

        $query = "SELECT id, description FROM acl_resource WHERE Type!='' $where ";
        $query .= "LIMIT ? OFFSET ?";
        $arrParam[] = $limit;
        $arrParam[] = $offset;
        $result = $this->_DB->fetchTable($query, true, $arrParam);

        if( $result == false )
        {
            $this->errMsg = $this->_DB->errMsg;
            return array();
        }

        return $result;
    }

	/**
	 * Procedimiento que devuelve todos los recursos a los que un grupo tiene permiso de acceso
	 * @param int   $id_group    ID del grupo del que se desea saber sus permisos
	 * @return array Un arreglo con todos los recursos a los que los los miembros del grupo dado tienen
					 acceso
			   false en caso de error
		array = ( array(resource_id ),
				  array(resource_id2)
				 )
	 */
    function loadGroupPermissions($id_group)
    {
		$where="";
		$result=false;
		if (!preg_match('/^[[:digit:]]+$/', "$id_group")) {
            $this->errMsg = _tr("Group ID must be numeric");
		}else{
			$arrParams=array($id_group);
			$query = "SELECT r.id FROM acl_resource r where r.id in ( Select ogr.id_resource from organization_resource ogr JOIN group_resource gr on gr.id_org_resource=ogr.id WHERE gr.id_group=? ) and Type!=''";
			$result = $this->_DB->fetchTable($query,true,array($id_group));
			if( $result === false ) {
				$this->errMsg = $this->_DB->errMsg;
			}
		}
        return $result;
    }


	function saveOrgPermission($idOrganization, $resources){
        if (!preg_match('/^[[:digit:]]+$/', "$idOrganization")){
            $this->errMsg = _tr("Organization ID is not valid");
			return false;
        }else{
			$sPeticionSQL = "INSERT INTO organization_resource (id_organization, id_resource) ".
                                "VALUES(?,?)";
            foreach ($resources as $resource)
            {
				//validamos que exista el recurso
				$query="SELECT 1 from acl_resource where id=?";
				$result=$this->_DB->getFirstRowQuery($query,false, array($resource));
                if($result==false){
					$this->errMsg = _tr("Doesn't exist resource with id=").$resource." ".$this->_DB->errMsg;
					return false;
				}

				//validamos que exista la organizacion
				$query="SELECT 1 from organization where id=?";
				$result=$this->_DB->getFirstRowQuery($query,false, array($idOrganization));
				if($result==false){
					$this->errMsg = _tr("Doesn't exist organization with id=").$idOrganization." ".$this->_DB->errMsg;
					return false;
				}

				if (!$this->_DB->genQuery($sPeticionSQL, array($idOrganization, $resource))){
					$this->errMsg = $this->_DB->errMsg;
					return false;
				}
            }
        }
		return true;
	}

	
    function saveGroupPermissions($idGroup, $resources)
    {
        if (!preg_match('/^[[:digit:]]+$/', "$idGroup")){
            $this->errMsg = _tr("Group ID is not valid");
			return false;
        }else{
			$sPeticionSQL = "INSERT INTO group_resource (id_group, id_org_resource) VALUES(?,?)";
            foreach ($resources as $resource)
            {
				//validamos que exista el recurso
				$query1="SELECT 1 from acl_resource where id=?";
				$result1=$this->_DB->getFirstRowQuery($query1,false, array($resource));
                if($result1==false){
					$this->errMsg = _tr("Doesn't exist resource with id=").$resource." ".$this->_DB->errMsg;
					return false;
				}

				//validamos que exista el grupo
				$query2="SELECT id_organization from acl_group where id=?";
				$result2=$this->_DB->getFirstRowQuery($query2,false, array($idGroup));
				if($result2==false){
					$this->errMsg = _tr("Doesn't exist group with id=").$idGroup." ".$this->_DB->errMsg;
					return false;
				}

				//validamos que la organization a la que pertenece el grupo tenga acceso a ese recurso tambien
				$query3="SELECT id from organization_resource where id_organization=? and id_resource=?";
				$result3=$this->_DB->getFirstRowQuery($query3,false, array($result2[0],$resource));
				if($result3==false){
					$this->errMsg = _tr("Organization doesn't have priviled to access resource ").$resource." ".$this->_DB->errMsg;
					return false;
				}

				if (!$this->_DB->genQuery($sPeticionSQL, array($idGroup,$result3[0]))){
					$this->errMsg = $this->_DB->errMsg;
					return false;
				}
            }
        }
        return true;
    }


	function deleteOrgPermissions($idOrganization, $resources)
    {
        if (!preg_match('/^[[:digit:]]+$/', "$idOrganization")){
            $this->errMsg = _tr("Organization ID is not valid");
			return false;
        }else {
			$queryId = "SELECT id FROM organization_resource where id_organization = ? AND id_resource = ?";
			$dOrgResorc = "DELETE FROM organization_resource WHERE id = ?";
            foreach ($resources as $resource){
				$result=$this->_DB->getFirstRowQuery($queryId,false, array($idOrganization, $resource));
				if($result===false){
					$this->errMsg = _tr("Error has ocurred to delete permission")." ".$this->_DB->errMsg;
					return false;
				}elseif(count($result)>0){
					if (!$this->_DB->genQuery($dOrgResorc, array($result[0]))){
						$this->errMsg = $this->_DB->errMsg;
						return false;
					}
				}
            }
        }
        return true;
    }


    function deleteGroupPermissions($idGroup, $resources)
    {
        if (!preg_match('/^[[:digit:]]+$/', "$idGroup")){
            $this->errMsg = _tr("Group ID is not valid");
			return false;
        }else{
			//borramos la entrada en la tabla group_resource
			$query2 = "DELETE FROM group_resource WHERE id_group = ? AND id_org_resource = (SELECT o.id from organization_resource o JOIN acl_group g on o.id_organization=g.id_organization where o.id_resource=? and g.id=?)";
            foreach ($resources as $resource){
                if (!$this->_DB->genQuery($query2, array($idGroup, $resource, $idGroup))){
                    $this->errMsg = $this->_DB->errMsg;
                    return false;
                }
            }
        }
        return true;
    }


    /**
     * Procedimiento para eliminar el recurso dado su id. 
     * Antes de eliminar el recurso se debe elminar las entradas de dicho recurso de las tabla group_resource
     * y organization_resource
     * @param integer   $idresource
     *
     * @return bool     si es verdadero entonces se elimino bien
     ******************************************************************/
    function deleteResource($idresource)
    {
        $this->errMsg = "";
        $query = "DELETE FROM acl_resource WHERE id = ?";
        $result = $this->_DB->genQuery($query,array($idresource));
        if($result==FALSE){
            $this->errMsg = $this->_DB->errMsg;
            return false;
        }else
            return true;
    }


     /**
     * Procedimiento para obtener el nombre del grupo dado un id. 
     *
     * @param integer   $idGroup  id del grupo
     *
     * @return string    nombre del grupo 
     */
    function getGroupNameByid($idGroup)
    {
        $groupName = null;
        $this->errMsg = "";
        $data = array($idGroup);
        $sPeticionSQL = "SELECT name FROM acl_group WHERE id = ?";
        $result = $this->_DB->getFirstRowQuery($sPeticionSQL, FALSE, $data);
        if ($result && is_array($result) && count($result)>0) {
            $groupName = $result[0];
        }else $this->errMsg = $this->_DB->errMsg;
        return $groupName;
    }

    function updateUserName($idUser, $name){
        if(!preg_match("/[[:digit:]]+/",$idUser)){
            $this->errMsg=_tr("User ID is not valid");
            return false;
        }
        $query="Update acl_user set name=? where id=?";
        $result = $this->_DB->genQuery($query,array($name,$idUser));
        if($result==false){
            $this->errMsg = $this->_DB->errMsg;
            return false;
        }else
            return true;
    }
    
    /**
     * Esta funcion retorna si un usuario identificado por su username tiene permisos
     * de realizar cierta action dentro de un modulo elastix que es identificado por 
     * su nombre
     */
    function userCanPerformAction($moduleId,$action,$userAccount){
        $query="SELECT ogr.id_resource From organization_resource as ogr ".
                    "JOIN group_resource_actions as gr on ogr.id=gr.id_org_resource ".
                        "WHERE gr.id_action=? AND ogr.id_resource=? ".
                        "AND gr.id_group=(Select u.id_group from acl_user as u where u.username=?)";
        $result=$this->_DB->fetchTable($query,false,array($action,$moduleId,$userAccount));
        if(is_array($result) && count($result)>0){
            return true;
        }else
            return false;
    }
}
?>