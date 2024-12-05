<?php
class DB
{
        # @object, The PDO object
        private $pdo;

        # @object, PDO statement object
        private $sQuery;

        # @bool , Connected to the database
        private $bConnected = false;

        # @object, Object for logging exceptions        
        private $log;

        # @array, The parameters of the SQL query
        private $parameters;
        
       /**
        *        This method makes connection to the database.
        *        
        *        1. Reads the database settings from a ini file.
        *        2. Puts the ini content into the settings array.
        *        3. Tries to connect to the database.
        *        4. If connection failed, exception is displayed and a log file gets created.
        */
                private function Connect()
                {
                    $dsn = 'sqlite:'.SQLi_PATH.SQLi_NAME;
                    try
                    {
                        $this->pdo = new PDO($dsn);
                        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                        $this->bConnected = true;
                    }
                    catch (PDOException $e)
                    {
                        echo $this->ExceptionLog($e->getMessage());
                        die();
                    }
                }
       /**
        *        Every method which needs to execute a SQL query uses this method.
        *        
        *        1. If not connected, connect to the database.
        *        2. Prepare Query.
        *        3. Parameterize Query.
        *        4. Execute Query.        
        *        5. On exception : Write Exception into the log + SQL query.
        *        6. Reset the Parameters.
        */        
                private function Init($query, $parameters = "")
                {
                    if (!$this->bConnected) { 
                        $this->Connect(); 
                    }
                
                    try {
                        $this->sQuery = $this->pdo->prepare($query);
                        $this->bindMore($parameters);
                        $this->succes = $this->sQuery->execute();
                    } catch (PDOException $e) {
                        echo $this->ExceptionLog($e->getMessage(), $query);
                        die();
                    }
                
                    $this->parameters = array(); // Сброс параметров
                }
                
       /**
        *        @void
        *
        *        Add the parameter to the parameter array
        *        @param string $para
        *        @param string $value
        */        
                public function bind($para, $value)
                {        
                        $this->parameters[sizeof($this->parameters)] = ":" . $para . "\x7F" . $value;
                }
       /**
        *        @void
        *        
        *        Add more parameters to the parameter array
        *        @param array $parray
        */        
                public function bindMore($parray)
                {
                        if(empty($this->parameters) && is_array($parray)) {
                                $columns = array_keys($parray);
                                foreach($columns as $i => &$column)        {
                                        $this->bind($column, $parray[$column]);
                                }
                        }
                }
       /**
        *         If the SQL query contains a SELECT statement it returns an array containing all of the result set row
        *        If the SQL statement is a DELETE, INSERT, or UPDATE statement it returns the number of affected rows
        *
        *         @param string $query
        *        @param array $params
        *        @param int $fetchmode
        *        @return mixed
        */                        
                public function query($query,$params = null,$fetchmode = PDO::FETCH_ASSOC,$fetchclass = null)
                {
                        $query = trim($query);

                        $this->Init($query,$params);

                        if (stripos($query, 'select') === 0){
							if ($fetchmode == 8) {
								return $this->sQuery->fetchAll(PDO::FETCH_CLASS, $fetchclass);
								}
							else {
							    return $this->sQuery->fetchAll($fetchmode);
							}
                        }
                        elseif (stripos($query, 'insert') === 0 || stripos($query, 'update') === 0 || stripos($query, 'delete') === 0) {
                                return $this->sQuery->rowCount();        
                        }        
                        else {
                                return NULL;
                        }
                }
                
      /**
* Returns the last inserted id.
* @return string
*/        
                public function lastInsertId() {
                        return $this->pdo->lastInsertId();
                }        
                
       /**
        *        Returns an array which represents a column from the result set
        *
        *        @param string $query
        *        @param array $params
        *        @return array
        */        
                public function column($query,$params = null)
                {
                        $this->Init($query,$params);
                        $Columns = $this->sQuery->fetchAll(PDO::FETCH_NUM);                
                        
                        $column = null;

                        foreach($Columns as $cells) {
                                $column[] = $cells[0];
                        }

                        return $column;
                        
                }        
       /**
        *        Returns an array which represents a row from the result set
        *
        *        @param string $query
        *        @param array $params
        *         @param int $fetchmode
        *        @return array
        */        
                public function row($query,$params = null,$fetchmode = PDO::FETCH_ASSOC)
                {                                
                        $this->Init($query,$params);
                        return $this->sQuery->fetch($fetchmode);                        
                }
       /**
        *        Returns the value of one single field/column
        *
        *        @param string $query
        *        @param array $params
        *        @return string
        */        
                public function single($query,$params = null)
                {
                        $this->Init($query,$params);
                        return $this->sQuery->fetchColumn();
                }
       /**        
        * Writes the log and returns the exception
        *
        * @param string $message
        * @param string $sql
        * @return string
        */
        private function ExceptionLog($message , $sql = "")
        {
                $message = "Unhandled Exception from PDO-DB-Class: ".$message." |||| Raw SQL: ".$sql;
                $message = trim(preg_replace('/\s\s+/', ' ', $message));
                error_log($message,0); 
                
                return $message;
        }
        
        
         /**
     * Возвращает все строки результата запроса
     *
     * @param string $query
     * @param array $params
     * @param int $fetchmode
     * @param string|null $fetchclass
     * @return mixed
     */                        
    public function getAll($query, $params = null, $fetchmode = PDO::FETCH_ASSOC, $fetchclass = null){
        $this->Init($query, $params);

        if (stripos($query, 'select') === 0)
        {
            if ($fetchmode == PDO::FETCH_CLASS) {
                return $this->sQuery->fetchAll(PDO::FETCH_CLASS, $fetchclass);
            }
            else {
                return $this->sQuery->fetchAll($fetchmode);
            }
        }

        return null;
    }

    /**
     * Возвращает одну строку результата запроса
     *
     * @param string $query
     * @param array $params
     * @param int $fetchmode
     * @return array
     */        
    public function getRow($query, $params = null, $fetchmode = PDO::FETCH_ASSOC)
    {                                
        $this->Init($query, $params);
        return $this->sQuery->fetch($fetchmode);                        
    }

    /**
     * Добавляет новую запись в базу данных
     *
     * @param string $table
     * @param array $data
     * @return int
     */
    public function add($table, $data){
        $fields = implode(', ', array_keys($data));
        $values = ':' . implode(', :', array_keys($data));
        $query = "INSERT INTO $table ($fields) VALUES ($values)";
        
        $this->Init($query, $data);
        return $this->lastInsertId();
    }

}