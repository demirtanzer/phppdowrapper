<?php
class db extends PDO {
	private $error;
	private $sql;
	private $bind;
	private $errorCallbackFunction;
	private $errorMsgFormat;

	private function debug() {
		if(!empty($this->errorCallbackFunction)) {
			$error = array("Error" => $this->error);
			if(!empty($this->sql)) $error["SQL Statement"] = $this->sql;
			if(!empty($this->bind)) $error["Bind Parameters"] = trim(print_r($this->bind, true));
			ini_set("memory_limit","256M");
			$backtrace = debug_backtrace();
			if(!empty($backtrace)) {
				foreach($backtrace as $info) {
					if($info["file"] != __FILE__) $error["Backtrace"] = $info["file"] . " at line " . $info["line"];	
				}		
			}
			$msg = "";
			if($this->errorMsgFormat == "html") {
				if(!empty($error["Bind Parameters"])) $error["Bind Parameters"] = "<pre>" . $error["Bind Parameters"] . "</pre>";
				$css = trim(file_get_contents(dirname(__FILE__) . "/class.db.style/error.css"));
				$msg .= '<style type="text/css">' . "\n" . $css . "\n</style>";
				$msg .= "\n" . '<div class="db-error">' . "\n\t<h3>SQL Error</h3>";
				foreach($error as $key => $val) $msg .= "\n\t<label>" . $key . ":</label>" . $val;
				$msg .= "\n\t</div>\n";
			} 
			elseif($this->errorMsgFormat == "text") {
				$msg .= "SQL Error\n" . str_repeat("-", 50);
				foreach($error as $key => $val) $msg .= "\n\n$key:\n$val";
			}
			$func = $this->errorCallbackFunction;
			$func($msg);
		}
	}

	private function filter($table, $info) {
		$driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
		if($driver == 'sqlite') {
			$sql = "PRAGMA table_info('" . $table . "');";
			$key = "name";
		} 
		elseif($driver == 'mysql') {
			$sql = "DESCRIBE " . $table . ";";
			$key = "Field";
		} 
		else {	
			$sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '" . $table . "';";
			$key = "column_name";
		}
		if(false !== ($list = $this->run($sql))) {
			$fields = array();
			foreach($list as $record) $fields[] = $record[$key];
			return array_values(array_intersect($fields, array_keys($info)));
		}
		return array();
	}

	private function cleanup($bind) {
		if(!is_array($bind)) {
			if(!empty($bind)) $bind = array($bind);
			else $bind = array();
		}
		return $bind;
	}

	public function __construct($host, $dbname, $charset="", $user="", $passwd="") {
		$options = array(
			PDO::ATTR_PERSISTENT => true, 
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
		);
		try {
			parent::__construct('mysql:host=' . $host . ';dbname=' . $dbname . ';charset=' . $charset, $user, $passwd, $options);
		} catch (PDOException $e) {
			$this->error = printf($e->getMessage()).$host.$dbname.$charset.$user.$passwd.$options;
		}
	}

	public function delete($table, $where, $bind="") {
		$sql = "DELETE FROM " . $table . " WHERE " . $where . ";";
		return $this->run($sql, $bind) !== false;
	}

	public function insert($table, $info) {
		$fields = $this->filter($table, $info);
		$sql = "INSERT INTO " . $table . " (" . implode(", ",$fields) . ") VALUES (:" . implode(", :",$fields) . ");";
		$bind = array();
		foreach($fields as $field) $bind[":$field"] = $info[$field];
		return $this->run($sql, $bind);
	}

	public function run($sql, $bind="") {
		$this->sql = trim($sql);
		$this->bind = $this->cleanup($bind);
		$error = $this->error;
		$this->error = "";
		try {
			if((@$pdostmt = $this->prepare($this->sql))===NULL){
				$css = trim(file_get_contents(dirname(__FILE__) . "/class.db.style/error.css"));
				$msg 	= '<style type="text/css">' . "\n" . $css . "\n</style>";
				$msg .= "\n" . '<div class="db-error">' . "\n\t<h3>SQL Database Connect Error</h3>";
				if(!empty($error)) $msg .= "\n\t<label>" . $error . "</label>";
				$msg .= "\n</div>";
				echo $msg;
				exit;
			}
			if($pdostmt->execute($this->bind) !== false) {
				if(preg_match("/^(" . implode("|", array("select", "describe", "pragma")) . ") /i", $this->sql)) return $pdostmt->fetchAll(PDO::FETCH_ASSOC);
				elseif(preg_match("/^(" . implode("|", array("delete", "insert", "update")) . ") /i", $this->sql)) return $pdostmt->rowCount();
			}
		}
		catch (PDOException $e) {
			$this->error = $e->getMessage();	
			$this->debug();
			return false;
		}
	}

	public function select($table, $where="", $bind="", $fields="*") {
		$sql = "SELECT " . $fields . " FROM " . $table;
		if(!empty($where)) $sql .= " WHERE " . $where;
		$sql .= ";";
		return $this->run($sql, $bind);
	}

	public function setErrorCallbackFunction($errorCallbackFunction, $errorMsgFormat="html") {
		if(in_array(strtolower($errorCallbackFunction), array("echo", "print"))) $errorCallbackFunction = "print_r";
		if(function_exists($errorCallbackFunction)) {
			$this->errorCallbackFunction = $errorCallbackFunction;	
			if(!in_array(strtolower($errorMsgFormat), array("html", "text"))) $errorMsgFormat = "html";
			$this->errorMsgFormat = $errorMsgFormat;	
		}	
	}

	public function update($table, $info, $where, $bind="") {
		$fields = $this->filter($table, $info);
		$fieldSize = sizeof($fields);
		$sql = "UPDATE " . $table . " SET ";
		for($f = 0; $f < $fieldSize; ++$f) {
			if($f > 0) $sql .= ", ";
			$sql .= $fields[$f] . " = :update_" . $fields[$f]; 
		}
		$sql .= " WHERE " . $where . ";";
		$bind = $this->cleanup($bind);
		foreach($fields as $field) $bind[":update_$field"] = $info[$field];
		return $this->run($sql, $bind);
	}
	public function upsert($table, $info, $uniqueKey){
    $fields = $this->filter($table, $info);
    $columns = implode(", ", $fields);
    $placeholders = ":" . implode(", :", $fields);
    $updates = [];
    foreach ($fields as $f) {
			if ($f !== $uniqueKey) {
				$updates[] = "$f = VALUES($f)";
			}
    }
    $updateSql = implode(", ", $updates);
    $sql = "INSERT INTO $table ($columns)
            VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE $updateSql;";
    $bind = [];
    foreach ($fields as $field) {
			$bind[":$field"] = $info[$field];
    }
    return $this->run($sql, $bind);
	}
}	