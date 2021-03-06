<?php
final class User extends ModelBase {
    
	/**
	 * User ID
	 * @var int
	 */
	private $uid = 0;
	
	/**
	 * Username
	 * @var string
	 */
	private $username;
	
	/**
	 * Nickname
	 * @var string
	 */
	private $nickname;
	
	/**
	 * New Password
	 * @var string
	 */
	private $password;
	
	/**
	 * Current password (hashed)
	 * @var string
	 */
	private $curPassword;
	
	/**
	 * Salt
	 * @var string
	 */
	private $salt;
	
	/**
	 * Mail adress
	 * @var string
	 */
	private $email;
	
	/**
	 * Is Admin?
	 * @var boolean
	 */
	private $isAdmin = false;
	
	/**
	 * Design
	 * @var string
	 */
	private $design = 'normal';
	
	/**
	 * Last login
	 * @var int
	 */
	private $last_login;
	
	/**
	 * Language
	 * @var string
	 */
	private $lang;
	
	/**
	 * Constructor
	 * @param mixed An User Identiefier either the numeeric User ID or String username
	 */
	public function __construct() { }
	
	protected function assign(array $row) {
		$this->isNewRecord	= false;
		
		$this->uid			= $row['_id'];
		$this->username		= $row['username'];
		$this->nickname	= $row['nickname'];
		
		$this->password		= NULL;
		$this->curPassword	= $row['password'];
		$this->salt			= $row['salt'];
		
		$this->email		= $row['email'];
		$this->isAdmin		= (bool)$row['admin'];
		$this->last_login	= $row['last_login'];
		$this->lang			= $row['lang'];
		
		$this->design		= $row['design'];
	}

	/**
	 * Login with clearpaswd
	 * @param String Cleartext Password
	 * @return bool Success
	 */
	public function login($clearPswd) {
		if(Utils::createPasswordHash($clearPswd, $this->salt) == $this->curPassword) {
			System::getSession()->setUID($this->uid);
				
			$this->last_login = time();
			$this->save();
				
			return true;
		}
		
		return false;
	}
	
	/**
	 * Saves changes to DB
	 */
	public function save() {
		$data = array(
			':username' => $this->username,
			':email' => $this->email,
			':lang' => $this->lang,
			':last_login' => $this->last_login,
			':nickname' => $this->nickname,
			':admin'	=> $this->isAdmin,
			':design'	=> $this->design
		);
		
		if($this->isNewRecord) {
			// TODO: Check Password!	
			$data[':pswd'] = $this->password;
			$data[':salt'] = $this->salt;
			
			$sql = System::getDatabase()->prepare('INSERT INTO users (username, nickname, email, admin, lang, password, salt, last_login, design) VALUES(:username, :nickname, :email, :admin, :lang, :pswd, :salt, :last_login, :design)');
			$sql->execute($data);
			
			$this->uid = System::getDatabase()->lastInsertId();
		} else {
			$data[':uid']	= $this->uid;
			
			if($this->password != NULL) {
				$data[':pswd'] = $this->password;
				$data[':salt'] = $this->salt;	
				$sql = System::getDatabase()->prepare('UPDATE users SET username = :username, nickname = :nickname, email = :email, admin = :admin, lang = :lang, password = :pswd, salt = :salt, last_login = :last_login, design = :design WHERE _id = :uid');
			} else {
				$sql = System::getDatabase()->prepare('UPDATE users SET username = :username, nickname = :nickname, email = :email, admin = :admin, lang = :lang, last_login = :last_login, design = :design WHERE _id = :uid');
			}
			
			$sql->execute($data);
		}
	}
	
	public function delete() {
		
		// Delete user
		$sql = System::getDatabase()->prepare('DELETE FROM users WHERE _id = :id');
		$sql->execute(array(':id' => $this->uid));
        
        Log::sysLog("User", "User ".$this->getFullname()." was deleted");
	}
	
	/**
	 * Global setter
	 * @param string Property name
	 * @param mixed Property value
	 */
	public function __set($property, $value) {
		if($property == 'uid') {
			throw new InvalidArgumentException('UID is read-only and cannot be set');	
		}
		
		if($property == 'password' && !empty($value)) {
			$this->salt = Utils::createPasswordSalt();
			$value = Utils::createPasswordHash($value, $this->salt);
		}
		
		if(property_exists($this, $property)) {
			$this->$property = $value;	
		} else {
			throw new InvalidArgumentException('Property '.$property.' does not exist (class: '.get_class($this).')');
		}
	}
	
	/**
	 * Global getter
	 * @param string Property name
	 */
	public function __get($property) {
		if(property_exists($this, $property)) {
			return $this->$property;	
		}
		
		throw new InvalidArgumentException('Property '.$property.' does not exist (class: '.get_class($this).')');
	}
	
	/**
	 * Returns full name
	 * @return string Fullname
	 */
	public function getFullname() {
		if(empty($this->nickname)) {
			return '';
		} else {
			return trim($this->nickname);
		}
		return trim($this->nickname);	
	}
	
	public function __toString() {
		return $this->getFullname();	
	}
	
	public function getFolders() {
		$list = array();
		
		$folders = Folder::find('user_ID', $this->uid);
		
		if(is_array($folders)) {
			$list = $folders;	
		} else if($folders != NULL) {
			$list[] = $folders;	
		}
		
		return $list;	
	}
	
	
	
	
	
	
	public static function find($column = '*', $value = NULL, array $options = array()) {
		$query = 'SELECT * FROM users';
		$params = array();
		
		if($column != '*' && strlen($column) > 0 && $value != NULL) {
			$query .= ' WHERE '.Database::makeTableOrColumnName($column).' = :value';
			$params[':value'] = $value;
		}
		
		if(isset($options['orderby']) && isset($options['sort'])) {
			$query .= ' ORDER BY '.Database::makeTableOrColumnName($options['orderby']).' ' . strtoupper($options['sort']);
		}
		
		if(isset($options['limit'])) {
			$query .= ' LIMIT ' . $options['limit'];
		}
			
		$sql = System::getDatabase()->prepare($query);
		$sql->execute($params);
		
		if($sql->rowCount() == 0) {
			return NULL;	
		} else if($sql->rowCount() == 1) {
			$user = new User();
			$user->assign($sql->fetch());
			
			return $user;
		} else {
			$list = array();
			
			while($row = $sql->fetch()) {

				$user = new User();
				$user->assign($row);
				
				$list[] = $user;	
			}
			return $list;
		}
	}
	
	public static function compare($a, $b) {
		return strcmp($a->username, $b->username);	
	}
}
?>
