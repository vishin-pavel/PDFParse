<?php
/**
 * Класс шаблонизатора. На входе получает файл текстового формата, на выходе книгу
 */
class Converter{
	private $_handler;
	private $filePath;
	private $bookPath = '/book/';

	public function __construct($file){
		$this->filePath=$file;
	}

	public function run(){
		$this->_handler= $this->get_handler_instance();
	}
	private function get_handler_instance(){
		$matches = array();
		preg_match('/.+\.(\w+)$/',$this->filePath,$matches);
		$handlerName = strtoupper($matches[1]).'\\'.strtoupper($matches[1]).'Engine';
		$this->_handler = new $handlerName($this->filePath);
		$page = $this->_handler-> get_page(1);
	}
}
