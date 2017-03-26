<?php
	
	require_once("Rest.inc.php");
	
	class API extends REST {
	
		public function __construct(){			
			// Initiate parent contructor
			parent::__construct();
		}
		
		// Public method for access api.
		// This method dynmically call the method based on the query string
		public function processApi() {
			$method = strtolower(trim(str_replace("/", "", $_REQUEST['request'])));
			if((int)method_exists($this, $method) > 0) {
				$this->$method();
			}
			else {
				// If the method not exist with in this class, response would be "Page not found".
			 	$this->response('',404);
			}
		}

		// json encoding
		private function json($data) {
			if(is_array($data)) {
				return json_encode($data);
			}
		}

		// flattenTree gets the list of all the nodes in any tree
		private function flattenTree ($tree, &$list) {
			foreach ($tree as $key => $value) {
				array_push($list, $key);
				if (is_array($value)) {
					$this->flattenTree($value, $list);
				}
				return;
			}
		}

		// getDistributors returns the list of distributors
		// from the distributors tree
		private function getDistributors() {
			$filecontent = file_get_contents("data/distributors.json");
			$distributors = json_decode($filecontent, true);
			$list = array();
			$this->flattenTree($distributors, $list);
			$this->response($this->json($list), 200);	
		}

		// addDistTree adds a new distributor to the distributors tree
		// This will not add if the hierarchy of distributors are not proper
		private function addDistTree(&$primary, $secondary) {
			if (sizeof($secondary) < 1) {
				return;
			}

			$key = array_shift($secondary);
			if (isset($primary[$key])) {
				// Creating new hierarchy of distributors
				if (!is_array($primary[$key])) {
					$primary[$key] = array();
				}
				$this->addDistTree($primary[$key], $secondary);
			}

			if (sizeof($secondary) < 1) {
				$primary[$key] = 1;
			}
			return;
		}

		// Wrapper around addDistTree
		private function storeDistributor() {
			if (isset($this->_request['name'])) {
				$name = $this->_request['name'];

				$filecontent = file_get_contents("data/distributors.json");
				$distributors = json_decode($filecontent, true);

				$levels = explode(",", $name);
				$this->addDistTree($distributors, $levels);

				$file = fopen("data/distributors.json", "w");
				fwrite($file, json_encode($distributors, true));
				fclose($file);

				$this->response($this->json(array('status' => "Success")), 200);
			}
			$this->response($this->json(array('status' => "Invalid entries")), 400);
		}

		
		// This adds the include and exclude permission to the permission tree
		private function addPermissionTree(&$primary, $secondary) {
			if (sizeof($secondary) < 1) {
				return;
			}

			$key = array_shift($secondary);	
			if (isset($primary[$key])) {
				if (!is_array($primary[$key])) {
					return;
				}
			}
			else {
				// Creating new hierarchy of permissions
				$primary[$key] = array();
			}
			
			$this->addPermissionTree($primary[$key], $secondary);
			if (sizeof($secondary) < 1) {
				$primary[$key] = 1;
			}

			return;
		}

		// Add include permissions, this handles the hierarchy of permission
		// while adding permission for children
		private function includePermissions() {
			if (
				isset($this->_request['name'])
				&& isset($this->_request['values'])
			) {
				$name = $this->_request['name'];
				$filecontent = file_get_contents("data/permissions.json");
				$allpermissions = json_decode($filecontent, true);
				$includes = explode(",", $this->_request['values']);
					
				foreach ($includes as $value) {
					$locations = explode("-", $value);
					$levels = array($name, "includes");
					foreach ($locations as $loc) {
						array_push($levels, $loc);
					}
					if (isset($this->_request['ancestors'])) {
						$ancestors = explode(",", $this->_request['ancestors']);
						$parent = $ancestors[sizeof([$ancestors]) - 1];

						// Checks for the immediate parents include and exclude permission
						$parentinclude = $this->hasPermission($allpermissions[$parent]["includes"], $locations);
						$parentexclude = $this->hasPermission($allpermissions[$parent]["excludes"], $locations);

						if ($parentinclude && !$parentexclude) {
							$this->addPermissionTree($allpermissions, $levels);
						}
					}
					else {
						$this->addPermissionTree($allpermissions, $levels);
					}
				}

				$file = fopen("data/permissions.json", "w");
				fwrite($file, json_encode($allpermissions, true));
				fclose($file);
				$this->response($this->json(array('status' => "Success")), 200);
			}
			$this->response($this->json(array('status' => "Invalid entries")), 200);
		}

		// Checks whether the given permission level is eligible to include
		public function hasPermission(&$includes, $locations) {
			if (sizeof($locations) < 1) {
				return 0;
			}

			$loc = array_shift($locations);
			if (isset($includes[$loc])) {
				if (is_array($includes[$loc])) {
					return $this->hasPermission($includes[$loc], $locations);
				}
				else {
					return 1;
				}
			}
			return 0;
		}		

		// To exclude the permissions, check the include permission first before proceeding
		private function excludePermission() {
			if (isset($this->_request['name'])) {
				$name = $this->_request['name'];
				$filecontent = file_get_contents("data/permissions.json");
				$permissions = json_decode($filecontent, true);
				$flag = 1;
				
				if (isset($permissions[$name]) && isset($permissions[$name]["includes"])) {
					$excludes = explode(",", $this->_request['values']);
					foreach ($excludes as $value) {
						$locations = explode("-", $value);
						$check = $this->hasPermission($permissions[$name]["includes"], $locations);
					
						if ($check) {
							$levels = array($name, "excludes");
							foreach ($locations as $loc) {
								array_push($levels, $loc);
							}
							$this->addPermissionTree($permissions, $levels);
							$file = fopen("data/permissions.json", "w");
							fwrite($file, json_encode($permissions, true));
							fclose($file);
						}
						else {
							$flag = 0;
						}
					}
					if ($flag) {
						$this->response($this->json(array('status' => "Success")), 200);
					}
				}
				else {
					$this->response($this->json(array('status' => "Invalid entries")), 200);
				}
			}
		}

		// This is helper function to convert the given cities.csv
		// to a json format
		private function convertCSVtoTree() {
			$file = fopen("data/cities.csv", "r");
			while (($line = fgets($file)) !== false) {
				$area = explode(",", chop($line));
        		$locations[$area[2]][$area[1]][$area[0]] = 1;
    		}
		    fclose($file);

			$file = fopen("data/locations.json", "w");
			fwrite($file, json_encode($locations, true));
			fclose($file);
		}

		// To send the distributors JSON
		private function getDistributorsJSON () {
			$filecontent = file_get_contents("data/distributors.json");
			$distributors = json_decode($filecontent, true);
			$this->response($this->json($distributors), 200);
		}

		// Helper function to get the list of countries in the given list
		private function getCountries() {
			$filecontent = file_get_contents("data/locations.json");
			$locations = json_decode($filecontent, true);
			$countries = array_keys($locations);
			$this->response($this->json($countries), 200);
		}

		// Helper function to get the list of provinces for the given country
		private function getProvinces() {
			if (isset($this->_request['country'])) {
				$filecontent = file_get_contents("data/locations.json");
				$locations = json_decode($filecontent, true);
				$provinces = array_keys($locations[$this->_request['country']]);
				$this->response($this->json($provinces), 200);		
			}			
		}

		// Helper function to get the list of cities for the given city
		private function getCities() {
			if (isset($this->_request['country']) && isset($this->_request['province'])) {
				$filecontent = file_get_contents("data/locations.json");
				$locations = json_decode($filecontent, true);
				$cities = array_keys($locations[$this->_request['country']][$this->_request['province']]);
				$this->response($this->json($cities), 200);		
			}			
		}

		private function removeAllDistributors() {
			$file = fopen("data/distributors.json", "w");
			fwrite($file, "", true);
			fclose($file);
			$this->response($this->json(array("Status" => "Success")), 200);
		}

		private function removeAllPermissions() {
			$file = fopen("data/permissions.json", "w");
			fwrite($file, "", true);
			fclose($file);
			$this->response($this->json(array("Status" => "Success")), 200);
		}

		private function getAllPermissions() {
				$filecontent = file_get_contents("data/permissions.json");
				$permissions = json_decode($filecontent, true);
				$this->response($this->json($permissions), 200);		
		}

	}
	
	// Initiate Library	
	$api = new API;
	$api->processApi();
?>