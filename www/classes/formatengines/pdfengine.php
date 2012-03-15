<?php
namespace PDF;
/**
 * Класс разбирающий PHP на страници, картинки в них и наборы строк
 *
 */
class PDFEngine implements \PageFormatHandler{
	private $_PDFVersion='';
	private $filePath=NULL;
	private $filePointer=NULL;
	private $fileSize = 0;
	private $currentPage=0;
    public $root;
	public $RefTable=array();
	public $RefTableNext = array();
	public $pageTable=array();

    public $mapBook = array();
    public $keyMaintenance = array();

	public function __construct($file){
		$this->filePath= $file;
		$this->filePointer = fopen($this->filePath, 'rb');
		$this->fileSize = filesize($this->filePath);
		$this->get_ref_table();
		$this->get_page_table();

		//ToDo: Получить осноыную информацию о файле
		//Todo: Загрузить общие данные о дркументе
				//Todo: Загрузить список страниц
				//Todo: Загрузить Список шрифтов
				//Todo: Загрузить Таблицу объектов

	}
	/**
	 * Просто закрывает за собой файл
	 */
	public function __destruct(){
		fclose($this->filePointer);
	}
	/**
	 * Функция возвращает данные о запрошенной странице.
	 * @param int $pageNum
	 */
	public function get_page($pageNum = 1){
		$page = new Page($pageNum, $this);
        $page ->getMaintenance(); //Функция парсит содержание
        //$page -> getImg(); //Функция парсит картинки
        //return $page->get_text()->getImage()->parseHTML();

	}
	/**
	 * Функция парсит объект PDF типа stram. Возвращает его содержимое
	 * @param $objectID
	 * @return string
	 */
	protected function get_stream_content($objectID){
		$object = $this->get_obj_by_key($objectID);
		preg_match('/\/Filter\[?\s?(.*)\]?\W/', $object, $matches);
		preg_match('/(\s?\/(\w+))+/',$matches[1],$filter);
		preg_match('/stream(.*)endstream/ismU',$object, $streamcontent);
		$stream = trim($streamcontent[1]);
		if($filter[1]='FlateDecode'){
				return @gzuncompress($stream);

		}
		else return $stream;
	}
	/**
	 * Функция извлекает таблицу объектов содержащих страницы. Результаты раскидывает по свойствам объекта
	 * @return bool
	 */
	private function  get_page_table(){
		$currentObj = '';

		reset($this->RefTable);
		$key = key($this->RefTable);
		$matches = NULL;
		$nextPage = NULL;
		$pages = array();
		while((preg_match('/\x2F\x54\x79\x70\x65\x2F\x43\x61\x74\x61\x6C\x6F\x67/',$currentObj) != 1) ||
			  ($currentObj === false)
			 ){

			$currentObj = $this->get_obj_by_key($key);
			next($this->RefTable);
			$key = key($this->RefTable);
		}
		preg_match('/\x2F\x50\x61\x67\x65\x73\x20(\d+)\x20\x30\x20\x52/', $currentObj, $matches);
		$currentObj = $this->get_obj_by_key($matches[1]);
		preg_match('/\x2FKids\[(.*)\]/',$currentObj, $kids);
		preg_match_all('/\s?(\d+)\s\d+\sR/',$kids[1], $matches);
		foreach($matches[1] as $value){
			$pages[] = $value;
		}
		foreach($pages as $key => $value){
			if(isset($this->RefTable[$value])){
				$page=$this->get_obj_by_key($value);
				if(preg_match('/\/Type\/Page\W/',$page) == 1){
					$this->pageTable[] = $value;
				}
				$this->pageTable = array_merge($this->pageTable,$this->getChildren($value));
			}
		}
		return true;
	}
	/**
	 * Получить все дочерние страницы.
	 * @param $Obj
	 * @return array
	 */
	private function getChildren($Obj){
		$pages = array();
		$pagesArr = array();
		$currentObj = $this->get_obj_by_key($Obj);
		preg_match('/\x2FKids\[(.*)\]/',$currentObj, $kids);
		if(preg_match_all('/\s?(\d+)\s\d+\sR/',$kids[1], $matches) > 0){
			foreach($matches[1] as $value){
				$pages[] = $value;
			}
			foreach($pages as $key => $value){
				if(isset($this->RefTable[$value])){
					$page=$this->get_obj_by_key($value);
					if(preg_match('/\/Type\/Page\W/',$page) == 1){
						$pagesArr[] = $value;
					}
					$pagesArr = array_merge($pagesArr,$this->getChildren($value));
				}
			}
			return $pagesArr;
			}
		else return array();
	}

	/**
	 * Парсинг ссылочной таблицы. На объекты.
	 */
	private function get_ref_table(){
		$currentString = '';
		$matches=NULL;
		$tableLength = 0;
		$lastTable = false;

		fseek($this->filePointer, -32, SEEK_END);
		$nextTableLink='';
       // $nextTableLink = fgets($this->filePointer);
       // preg_match('/.*startxref.*?(\d+).*/', $nextTableLink, $xref);
       // $nextTableLink = (int)$xref['1'];

		while(preg_match('/startxref/', $nextTableLink)!=1 && $nextTableLink!==false){
			$nextTableLink = fgets($this->filePointer);
		}
		$nextTableLink = fgets($this->filePointer)+0;
		while($lastTable!== true){
			fseek($this->filePointer, $nextTableLink, SEEK_SET);
			fgets($this->filePointer);
			$currentString = fgets($this->filePointer);
			preg_match('/(\d+)\x20(\d+)/', $currentString, $matches);
			$tableLength = $matches[2];
			$startIndex = $matches[1];
			for($i=0; $i<$tableLength; $i++){
				$currentString = fgets($this->filePointer);
				preg_match('/(\d+)\x20\d+\x20\x6E/', $currentString, $matches);
				if(isset($matches[1]))
				$this->RefTable[$startIndex+$i]=$matches[1];
			}
			fgets($this->filePointer);
			$currentString = fgets($this->filePointer);
			if(preg_match('/\x2FPrev\x20(\d+)/', $currentString, $matches)==1) {
				$nextTableLink = $matches[1]+0;
            }
			else {
				$lastTable = true;
            }

            if(!isset($this -> root)) {
                  preg_match('/\x2FRoot\x20(\d+)/', $currentString, $root);
                  $this -> root = (int)$root[1];
            }
		}
		asort($this->RefTable, SORT_NUMERIC);
		reset($this->RefTable);
		$pointerKey=NULL;
		foreach($this->RefTable as $key => $value){
			if($pointerKey!=NULL)
				$this->RefTableNext[$pointerKey]=&$this->RefTable[$key];
			$pointerKey = $key;
		}
		if($pointerKey!=NULL)
			$this->RefTableNext[$pointerKey]= $nextTableLink;
	}
	/**
	 * Получение объекта по ключу(По адресу из перекрестной таблицы ссылок)
	 * @param $key
	 * @return string
	 */
	public function get_obj_by_key($key){
		fseek($this->filePointer, $this->RefTable[$key]);
		return fread($this->filePointer, $this->RefTableNext[$key]-$this->RefTable[$key]);
	}
	private function get_bin_obj($key){
		return file_get_contents($this->filePath, FILE_BINARY, NULL, $this->RefTable[$key], $this->RefTableNext[$key]-$this->RefTable[$key]);
	}

}

class AbstractPDFObject{
	private $properties = array();
	private $data = array();

	/**
	 * Получить элемент из массива данных объекта
	 * @param $paramType
	 * @return null
	 */
	public function get_data($paramType){
		if(isset($this->data[$paramType])){
			return $this->data[$paramType];
		}
		else return NULL;
	}
	/**
	 * Магический $__get возвращает элемент из data
	 * @param $name
	 * @return null
	 */
	public function __get($name){
		return $this->get_data($name);
	}
	/**
	 * Устанавливает значение в массив data
	 * @param $paramType
	 * @param $paramValue
	 * @return AbstractPDFObject
	 */
	public function set_data($paramType, $paramValue){
		if(!is_scalar($paramType) && !is_scalar($paramValue)){
			foreach($paramType as $key => $name){
				$this->data[$name] = $paramValue[$key];
			}
		}
		elseif(is_scalar($paramType) && is_scalar($paramValue))
			$this->data[$paramType] = $paramValue;
		return $this;
	}
	/**
	 * Устанавливает значение в массив propertis
	 * @param $paramType
	 * @param $paramValue
	 * @return AbstractPDFObject
	 */
	public function set_property($paramType, $paramValue){
		if(!is_scalar($paramType) && !is_scalar($paramValue)){
			foreach($paramType as $key => $name){
				$this->properties[$name] = $paramValue[$key];
			}
		}
		elseif(is_scalar($paramType) && is_scalar($paramValue))
			$this->properties[$paramType] = $paramValue;
		return $this;
	}
	/**
	 * Получить элемент из массива propertis
	 * @param $paramType
	 * @return null
	 */
	public function get_property($paramType){
		if(isset($this->properties[$paramType])){
			return $this->properties[$paramType];
		}
		else return NULL;
	}

}
/**
 * Объект страницы PDF
 */
class Page extends AbstractPDFObject{
	private $parentPDFEngine = NULL;
	private $properties = array(

	);
	private $data = array(
						'PageNum'=>0,
						'contObj'=>NULL,
						'text'=>'',
						'concatContent'=>'',
						'resources'=>array(),
                        'paramImg' => ''
						);
	/**
	 * Конструктор. При инициализации обзекта находит все ресурсы объекта и парсит их в атомарные для данной абстракции
	 * элементы:строки, изображения и т д
	 * Получает инстанс PDF обработчика
	 * @param $pageNum
	 * @param $PDFEngine
	 */
	public function __construct($pageNum, &$PDFEngine){
		$pageNum-=1;
		$this->parentPDFEngine = $PDFEngine;
		$object = $this->parentPDFEngine->get_obj_by_key($this->parentPDFEngine->pageTable[$pageNum]);
		preg_match('/\/Contents\[?([\d\sR]+)\]?/',$object, $contentId);
		preg_match_all('/\s*((\d+)\s\d+\sR)+/',$contentId[1], $contentId);
		foreach($contentId[2] as $key =>$value){
			$this->parse_stream($value);
		}
	}

	private function parse_stream($objectID){
		$object = $this->parentPDFEngine->get_obj_by_key($objectID);
		preg_match('/\/Filter\[?\s?\/?(\w+)\]?\W/i', $object, $matches);
		preg_match('/(\s?\/?(\w+))+/',$matches[1],$filter);
		preg_match('/stream(.*)endstream/ismU',$object, $streamcontent);
		$stream = trim($streamcontent[1]);
		if($filter[1]=='FlateDecode')
			$this->data['concatContent'] .= @gzuncompress($stream);
	}
	public function add_resource(){
		$this->data['resources'][] = new Resource;
		return end($this->data['resources']);
	}

    public function getImg() {

        preg_match_all('/q.*Q/ismUe',$this->data['concatContent'],$pageImg);

        for($i=0; $i<count($pageImg['0']);$i++) {

            preg_match('/\/image([\d]+)/ie',$pageImg['0'][$i],$parImg);
            $object = $this->parentPDFEngine->get_obj_by_key($parImg["1"]);

            preg_match('/stream(.*)endstream/ismU',$object, $codeImg);
            $codeImg[1] = trim($codeImg[1]);

            $this -> data['paramImg'][$i] = array();

            preg_match('/n(.*)cm/ismU',$pageImg['0'][$i], $coordinateImg);
            preg_match_all('/[\d]+[\.]?[\d]*/',$coordinateImg['1'], $coordinateImg);
            $this -> data['paramImg'][$i]['scaleWidth'] = (float)$coordinateImg['0']['0'];
            $this -> data['paramImg'][$i]['smehWidth'] = (float)$coordinateImg['0']['1'];
            $this -> data['paramImg'][$i]['smehHeight'] = (float)$coordinateImg['0']['2'];
            $this -> data['paramImg'][$i]['scaleHeight'] = (float)$coordinateImg['0']['3'];
            $this -> data['paramImg'][$i]['x'] = (float)$coordinateImg['0']['4'];
            $this -> data['paramImg'][$i]['y'] = (float)$coordinateImg['0']['5'];

            preg_match('/width[\s]?([0-9]+)/i',$object, $width);
            $this -> data['paramImg'][$i]['width'] = (float)$width['1'];

            preg_match('/height[\s]?([\d]+)/i',$object, $height);
            $this -> data['paramImg'][$i]['height'] = (float)$height['1'];
            $this -> data['paramImg'][$i]['nameImg'] = (string)'img'.$parImg['1'].'.jpg';

            $myImg = fopen('images/img'.$parImg['1'].'.jpg',"w");
            fwrite($myImg,$codeImg[1]);
            fclose($myImg);
        }

        return $this;
    }

    public function rekurs($first, $last) {

        $current = $first;
        while($current != $last) {

            $object = $this->parentPDFEngine->get_obj_by_key($current);
            preg_match('/(First\s(\d+)).*(Last\s(\d+))/',$object,$objMaintenance);
            $currentFirst = $objMaintenance['2'];
            $currentLast = $objMaintenance['4'];

            preg_match('/Next\s(\d+)/', $object, $objMaintenance);
            $this-> parentPDFEngine -> keyMaintenance[] = $objMaintenance['1'];

            preg_match('/Title\((.*)\)/',$object,$titleText);
            $data[] = $titleText['1'];
            $current = $objMaintenance['1'];

            if($currentFirst) {

                $data[] = $this -> rekurs($currentFirst, $currentLast);

            }
        }

        if(!$currentFirst) {

            $object = $this->parentPDFEngine->get_obj_by_key($current);
            preg_match('/Title\((.*)\)/',$object, $titleText);
            $data[] = $titleText['1'];

        }

        return $data;

    }

    public function getMaintenance() {

        $object = $this->parentPDFEngine->get_obj_by_key($this->parentPDFEngine->root);

        if(preg_match('/\/Outlines\s(\d+)/',$object,$objMaintenance) == true)
        {
            $object = $this->parentPDFEngine->get_obj_by_key($objMaintenance['1']);

            preg_match('/(First\s(\d+)).*(Last\s(\d+))/',$object,$objMaintenance);

            $first = $objMaintenance['2'];
            $last = $objMaintenance['4'];

            $this -> parentPDFEngine -> mapBook = $this -> rekurs($first, $last);

            return $this;

        }
        else
        {
            return false;
        }

    }
}
