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
        
        public function data_build($query, $return_data_string = false, $unbuffered = false)
	    {
		    $file_path = $this->df_name . "/";

            if (isset($query['SELECT'])) {
                $where_condition = isset($query['WHERE']) ? $query['WHERE'] : null;
                $results = $this->read_from_file($file_path.$query['FROM'].'.txt', $where_condition);
                return $return_data_string ? json_encode($results) : $results;
            } 
            else if (isset($query['INSERT'])) {
                $values = is_array($query['VALUES']) ? $query['VALUES'] : [$query['VALUES']];

                //Special treatment for search_words.txt
                if ($query['INTO'] === 'search_words') {
                    //Save words line by line
                    foreach ($values as $value) {
                        $value = trim($value, '"'); //Remove extra quotation marks
                        file_put_contents($file_path . $query['INTO'] . '.txt', $value.PHP_EOL, FILE_APPEND);
                    }
                } else {
                    //Default saving for other files
                    $data_to_save = json_encode($values);
                    $this->write_to_file($file_path.$query['INTO'].'.txt', $data_to_save);
                }
                
                $file_content = file($file_path.$query['INTO'].'.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $this->new_uid = count($file_content) + 1;
            }
            else if (isset($query['UPDATE'])) {
                $where_condition = isset($query['WHERE']) ? $query['WHERE'] : null;
                $new_data = $query['SET'];
                $this->update_file($file_path, $new_data, $where_condition);
            } 
            else if (isset($query['DELETE'])) {
                $where_condition = isset($query['WHERE']) ? $query['WHERE'] : null;
                $this->delete_from_file($file_path, $where_condition);
            }

            return true;
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
        
        private function write_to_file($file_path, $data)
        {
            file_put_contents($file_path, $data . PHP_EOL, FILE_APPEND);
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
        
        private function apply_condition($data, $condition)
        {
            //Example: word IN('example1', 'example2')
            if (preg_match('/(\w+)\s+IN\((.+)\)/', $condition, $matches)) {
                $field = $matches[1]; //The field being checked (e.g. 'word')
                $values = explode(',', str_replace("'", '', $matches[2])); //Values ​​against which the test is carried out

                //Check if the field exists and contains one of the values
                if (isset($data[$field]) && in_array($data[$field], $values)) {
                    return true;
                }
            }

            return false;
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