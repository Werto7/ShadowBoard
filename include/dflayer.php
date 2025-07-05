<?php
    class DFLayer {
    	public $df_name;
        public $prefix;
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
        
        public function get_new_uid($table = '')
        {
        	$file = $this->df_name . '/' . $table . '.json';

            if (!file_exists($file)) {
            	return 1;
            }

            $json = file_get_contents($file);
            $data = json_decode($json, true);

            if (!is_array($data) || empty($data)) {
            	return 1;
            }

            $ids = array_column($data, 'id');
            
            if (empty($ids)) {
            	return 1;
            }
            
            $ids = array_map('intval', $ids);
            
            return max($ids) + 1;
        }
	
	    public function search_in_file($filename, $select, $where)
        {
            $file_path = $this->df_name . '/' . $filename . '.json';

            if (!file_exists($file_path)) {
                throw new Exception("File not found: $file_path");
            }

            $content = file_get_contents($file_path);
            if (trim($content) === '') {
                return [];
            }

            $entries = json_decode($content, true);
            if (!is_array($entries)) {
                throw new Exception("Invalid JSON in $file_path");
            }

            // ----- Analyze WHERE: "word IN ('a','b','c')" -----
            if (!preg_match("/word\s+IN\s*\((.*?)\)/i", $where, $matches)) {
                throw new Exception("WHERE clause not recognized: $where");
            }

            $wordlist_raw = $matches[1]; //Content in brackets
            $wordlist = array_map(function ($w) {
                return trim($w, " \t\n\r\0\x0B'");
            }, explode(',', $wordlist_raw));

            // ----- Analyze SELECT: "$post_id, id, 1" -----
            $select_parts = array_map('trim', explode(',', $select));
            if (count($select_parts) !== 3) {
                throw new Exception("SELECT clause must contain exactly 3 fields: $select");
            }

            $post_id = $select_parts[0];
            $use_subject_flag = $select_parts[2];

            $results = [];

            foreach ($entries as $index => $entry) {
                if (!isset($entry['word'])) continue;

                $entry_words_raw = explode(',', $entry['word']);
                $entry_words = array_map(function ($w) {
                    return trim($w, " \t\n\r\0\x0B'");
                }, $entry_words_raw);

                foreach ($wordlist as $word) {
                    if (in_array($word, $entry_words, true)) {
                        $results[] = [$post_id, $index, $use_subject_flag];
                        break;
                    }
                }
            }

            return $results;
        }
        
        public function fetch_all_from_file($filename)
        {
            $file_path = $this->df_name . '/' . $filename . '.json';

            if (!file_exists($file_path)) {
                throw new Exception("File not found: $file_path");
            }

            $content = file_get_contents($file_path);
            if (trim($content) === '') {
                return [];
            }

            $entries = json_decode($content, true);
            if (!is_array($entries)) {
                throw new Exception("Invalid JSON in $file_path");
            }

            return $entries;
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
                            throw new Exception('Existing file contains invalid JSON.');
                        }

                        //If everything is okay: transfer existing data
                        $data_list = $existing_array;
                }
            }

            //Add new object
            $data_list[] = $json_array;

            $json_string = json_encode($data_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($json_string === false) {
                throw new Exception('Error converting to JSON: ' . json_last_error_msg());
            }

            if (file_put_contents($file_path, $json_string) === false) {
                throw new Exception('Error writing to file: ' . $file_path);
            }
        }
        
        public function trim_quotes_recursive($array) {
        	foreach ($array as $key => $value) {
        	    if (is_array($value)) {
        	        $array[$key] = $this->trim_quotes_recursive($value);
                } elseif (is_string($value)) {
                	if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                	    $array[$key] = substr($value, 1, -1);
                    }
                }
            }
            return $array;
        }
        
        public function update_entries_in_file(string $filename, string $set_field, mixed $set_value, ?callable $match_callback = null)
        {
        	$file_path = $this->df_name . '/' . $filename . '.json';

            if (!file_exists($file_path)) {
            	throw new Exception("File not found: $file_path");
            }

            $content = file_get_contents($file_path);
            if (trim($content) === '') {
            	return 0;
            }

            $entries = json_decode($content, true);
            if (!is_array($entries)) {
            	throw new Exception("Invalid JSON in $file_path");
            }

            $updated = 0;

            foreach ($entries as &$entry) {
            	if (is_null($match_callback) || $match_callback($entry)) {
            	    $entry[$set_field] = $set_value;
                    $updated++;
                }
            }

            file_put_contents($file_path, json_encode($entries, JSON_PRETTY_PRINT));

            return $updated; //Number of updated entries
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
            if (isset($this->result_set[$this->result_index])) {
                $cur_row = $this->result_set[$this->result_index];
                $this->result_index++;

                foreach ($cur_row as $key => $value) {
                    $dot_spot = strpos($key, '.');
                    if ($dot_spot !== false) {
                        unset($cur_row[$key]);
                        $key = substr($key, $dot_spot + 1);
                        $cur_row[$key] = $value;
                    }
                }

                return $cur_row;
            }

            // No further result, return false
            return false;
        }
    }
?>