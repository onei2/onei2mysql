<?php 
    /*
     *   onei2mysql - MySQL PHP helper class.
     *   Copyright (C) 2020  Ivan Ivanovic
     *
     *   This program is free software: you can redistribute it and/or modify
     *   it under the terms of the GNU General Public License as published by
     *   the Free Software Foundation, either version 3 of the License, or
     *   (at your option) any later version.
     *
     *   This program is distributed in the hope that it will be useful,
     *   but WITHOUT ANY WARRANTY; without even the implied warranty of
     *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     *   GNU General Public License for more details.
     *
     *   You should have received a copy of the GNU General Public License
     *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
     */

    namespace onei2;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    class mysql {
        public $host = 'localhost';
        public $user = 'root';
        public $pass = '';
        public $db = '';
        public $port = 3306;
        public $sql = '';
        public $dataset = [];
        public $charset = 'utf8mb4';
        public $success = FALSE;
        public $errorMsg = '';
        public $sqlList = []; //!!!
        
        function query($lenientMode = FALSE) {
            $this->dataset = [];

            $mysqli = new \mysqli($this->host, $this->user, $this->pass, $this->db, $this->port);

            if ($mysqli instanceof \mysqli) {
                if ($mysqli->connect_errno === 0) {
                    if ($mysqli->set_charset($this->charset)) {
                        if ($mysqli->begin_transaction(MYSQLI_TRANS_START_READ_WRITE)) {
                            $sql_array = explode(';', $this->sql);
                            $trimmed_sql_array = [];
        
                            foreach ($sql_array as $sql) {
                                if (strlen(trim($sql)) > 0) {
                                    array_push($trimmed_sql_array, trim($sql));
                                }
                            }
                            
                            $sql_array = NULL;

                            $error = FALSE;

                            foreach ($trimmed_sql_array as $sql) {
                                foreach ($_POST as $name => $value) {
                                    $sql = preg_replace("/\{\*{$name}\}/", $mysqli->real_escape_string($value), $sql);
                                    $sql = preg_replace("/\{{$name}\}/", $mysqli->real_escape_string(trim(preg_replace('/\s+/', ' ', $value))), $sql);
                                }

                                foreach ($_GET as $name => $value) {
                                    $sql = preg_replace("/\{\*{$name}\}/", $mysqli->real_escape_string($value), $sql);
                                    $sql = preg_replace("/\{{$name}\}/", $mysqli->real_escape_string(trim(preg_replace('/\s+/', ' ', $value))), $sql);
                                }
                                
                                foreach ($_SESSION as $name => $value) {
                                    $sql = preg_replace("/\{\@*{$name}\}/", $mysqli->real_escape_string($value), $sql);
                                    $sql = preg_replace("/\{@{$name}\}/", $mysqli->real_escape_string(trim(preg_replace('/\s+/', ' ', $value))), $sql);
                                }
                                
                                $sql = preg_replace('/&apos;/', "'", $sql);
                                $sql = preg_replace('/\{#\}/', $mysqli->insert_id, $sql);
                                
                                array_push($this->sqlList, $sql); //!!!    
                                $mysqli_result = $mysqli->query($sql);
                                
                                if ($mysqli_result && (($mysqli->affected_rows > 0) || $lenientMode)) { // $mysqli->affected_rows > 0
                                    if ($mysqli_result instanceof \mysqli_result) {
                                        array_push($this->dataset, $mysqli_result->fetch_all(MYSQLI_ASSOC));
                                        $mysqli_result->free_result();
                                    }
                                } else {
                                    $error = TRUE;
                                    break;
                                }
                            }

                            if ($error) {
                                $mysqli->rollback();
                                $this->success = FALSE;
                            } else {
                                $mysqli->commit();
                                $this->success = TRUE;
                            }
                        }
                    }
                } else {
                    $this->errorMsg = $mysqli->connect_error;
                }

                $mysqli->close();
            }
        }
        
        function forEachRow($datasetIndex, $callback) {
            if (is_callable($callback) && isset($this->dataset[$datasetIndex])) {
                foreach ($this->dataset[$datasetIndex] as $rowIndex => $rowArray) {
                    $callback($rowIndex, $rowArray);
                }
            }
        }
    }
?>
