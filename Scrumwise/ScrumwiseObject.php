<?php

abstract class ScrumwiseObject
{
	protected $project;
	protected $data = [];
	protected $hasSetter = [];
	protected $canCreate = [];
	protected $canDelete = false;

	protected function __construct($project, $name) {
		$this->project = $project;
		$this->data['name'] = $name;
		$this->data['id'] = null;
	}

	public static function instantiate($project, $data) {
		$class = get_called_class();
		if($data['objectType'] != $class)
			return NULL;

		$obj = new $class($project, NULL);
		if($class == "Project")
			$project = $obj;

		unset($data['objectType']);
		foreach($data as $key => $val) {
			if(!isset($obj->$key)) {
				error_log("skipping '$class.$key' ...");
				continue;
			}
			if(is_array($val)) {
				$obj->data[$key] = [];
				foreach($val as $sub) {
					if(!isset($sub['objectType'])
					|| !class_exists($sub['objectType'])) {
						$obj->data[$key][] = $sub;
						continue;
					}
					$obj->data[$key][] = $sub['objectType']::instantiate($project, $sub);
				}
				continue;
			}
			$obj->data[$key] = $val;
		}
		return $obj;
	}

	protected function setter($method, $fields, $reqObj = false) {
		if($this->id == NULL) {
			if(!$reqObj || empty($canCreate))
				return;

			$this->create();
		}
		$class = get_class($this);

		$args = [];
		$args[lcfirst($class).'ID'] = $this->data['id'];
		foreach($fields as $field)
			$args[$field] = $this->data[$field];

		Scrumwise::call('set'.$class.$method, $args);
	}
	protected function save($field, $reqObj = false) {
		$this->setter(ucfirst($field), [$field], $reqObj);
	}

	public function create() {
		if(!is_array($this->canCreate))
			return $this->illegalCall();

		if(array_key_exists('projectID', $this->canCreate))
			$this->data['projectID'] = $this->project->id;

		$args = [];
		foreach($this->canCreate as $field => $val)
			$args[$field] = $this->data[$field];

		$this->data['id'] = Scrumwise::call('add'.get_class($this), $args);
	}
	public function delete() {
		if(!$this->canDelete)
			return $this->illegalCall();

		$class = get_class($this);
		$args = [];
		$args[lcfirst($class).'ID'] = $this->data['id'];
		Scrumwise::call('delete'.$class, $args);

		unset($this->data['id']);
		unset($this->data['projectID']);
	}
	public function __set($name, $value) {
		// ALLEEN in hasSetter[]:
		//		FAIL if not created	(proberen te createn ?)
		//		data[] update
		//		save()
		// ALLEEN in create[]:
		//		data[] update
		// BEIDE:
		//		data[] update
		//		save() if created

		if(!array_key_exists($name, $this->hasSetter)
		|| !array_key_exists($name, $this->data))
			return $this->illegalCall();

		$m = 'validate'.ucfirst($name);
		if(method_exists($this, $m))
			$value = call_user_method($m, $this, $value);

		if($this->data[$name] == $value)
			return;

		$this->data[$name] = $value;
		$this->save($name);
	}
	public function &__get($name) {
		if(!array_key_exists($name, $this->data))
			return $this->illegalCall();

		return $this->data[$name];
	}
	public function __isset($name) {
		return array_key_exists($name, $this->data);
	}
	public function __unset($name) {
		return $this->__set($name, NULL);
	}

	private function illegalCall() {
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		error_log('Illegal call to '.$trace[1]['function'].'() '
						. ' in ' . $trace[1]['file']
						.  ' on line ' . $trace[1]['line']);
		return NULL;
	}
}
