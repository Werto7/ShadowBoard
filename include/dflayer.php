<?php
    class DFLayer {
    	public $df_name;
        private $new_uid;
        private $result_set = array();
        private $result_index = 0; //For tracking the current position in the array
        private $extension_hooks = array("id", "extension_id", "code", "installed", "priority");
        private $extensions = array("id", "title", "version", "description", "author", "uninstall", "uninstall_note", "disabled", "dependencies");
    
        public function __construct($df_name) {
        	$this->df_name = $df_name;
        }
        
    	public function escape($str)
	    {
		    return is_array($str) ? '' : $this->escapeStringForFile($str);
	    }
	
	    private function escapeStringForFile($str) {
        //Here we replace single quotes and backslashes
        $str = str_replace("'", "\'", $str); //Escaping single quotation marks
        $str = str_replace('"', '\"', $str); //Escaping double quotes
        $str = str_replace("\\", "\\\\", $str); //Backslash escaping

        return $str;
        }
        
        public function get_new_uid()
        {
            return $this->new_uid;
        }
	
	    private function read_from_file($file_path, $where_condition = null)
        {
        	if (!file_exists($file_path)) {
                return [];
            }

            $file_content = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $results = [];
            
            foreach ($file_content as $line) {
                $data = json_decode($line, true);

                // Apply WHERE condition (basic check)
                if ($where_condition === null || $this->apply_condition($data, $where_condition)) {
                $results[] = $data;
                }
            }

            return $results;
        }
        
        public function write_to_file($json_array, $filename_without_extension)
        {
            $file_path = $this->df_name . '/' . $filename_without_extension . '.json';

            $data_list = [];

            if (file_exists($file_path)) {
                $existing_content = file_get_contents($file_path);
                if (trim($existing_content) !== '') {
                        $existing_array = json_decode($existing_content, true);

                        if (!is_array($existing_array)) {
                            throw new Exception('Bestehende Datei enthält ungültiges JSON.');
                        }

                        // Falls alles okay ist: bestehende Daten übernehmen
                        $data_list = $existing_array;
                }
            }

            // Neues Objekt hinzufügen
            $data_list[] = $json_array;

            $json_string = json_encode($data_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($json_string === false) {
                throw new Exception('Fehler beim Umwandeln in JSON: ' . json_last_error_msg());
            }

            if (file_put_contents($file_path, $json_string) === false) {
                throw new Exception('Fehler beim Schreiben in Datei: ' . $file_path);
            }
        }
        
        private function update_file($file_path, $new_data, $where_condition)
        {
        	$file_content = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $updated_content = [];

            foreach ($file_content as $line) {
                $data = json_decode($line, true);

                // Apply WHERE condition
                if ($this->apply_condition($data, $where_condition)) {
                    $data = array_merge($data, json_decode($new_data, true)); // Update data
                }

                $updated_content[] = json_encode($data);
            }

            file_put_contents($file_path, implode(PHP_EOL, $updated_content) . PHP_EOL);
        }
        
        private function delete_from_file($file_path, $where_condition)
        {
            $file_content = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $updated_content = [];

            foreach ($file_content as $line) {
                $data = json_decode($line, true);

                // Apply WHERE condition
                if (!$this->apply_condition($data, $where_condition)) {
                    $updated_content[] = json_encode($data); // Keep only the non-matching data
                }
            }

            file_put_contents($file_path, implode(PHP_EOL, $updated_content) . PHP_EOL);
        }
        
        public function fetch_assoc($result = null)
        {
            //Check if a new result array has been passed
            if (is_array($result)) {
                //Reset the index and save the result
                $this->result_set = $result;
                $this->result_index = 0;
            }

            //Check if a valid result array exists and the index is within limits
            if (isset($this->result_set) && $this->result_index < count($this->result_set)) {
                //Get the current row from the result
                $cur_row = $this->result_set[$this->result_index];

                //Increment the index for the next call
                $this->result_index++;

                //Remove table names/aliases if they exist
                foreach ($cur_row as $key => $value) {
                    $dot_spot = strpos($key, '.');
                    if ($dot_spot !== false) {
                        unset($cur_row[$key]);
                        $key = substr($key, $dot_spot + 1);
                        $cur_row[$key] = $value;
                    }
                }

                return $cur_row; //Returns the processed row
            }

            // No further result, return false
            return false;
        }
    }
?>