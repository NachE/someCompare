<?php

define("SIMILITUDMINIMA", 15.9);
define("SIMILITUDMAXIMA", 60);

class boxCompared{

	private $asociated;
	private $indice;
	private $uid=-1;


	public function addSimil($idA, $idB, $microfecha){
		//echo "\n".$microfecha."\n";
		if(isset($this->indice[$idA])){
			if(isset($this->indice[$idB])){ 
				//echo "\nADo nothing\n";
			}else{

				if($this->indice[$idA] > $microfecha){
					$oldId=$this->indice[$idA];
					$this->indice[$idA]=$microfecha;
					$this->asociated[$microfecha]=$this->asociated[$oldId];
					unset($this->asociated[$oldId]);
				}

				$this->asociated[$this->indice[$idA]][]=$idB;
				$this->indice[$idB]=$this->indice[$idA];
			}

		}elseif(isset($this->indice[$idB])){
			if(isset($this->indice[$idA])){
				//echo "\nBDo nothing\n";
			}else{


				if($this->indice[$idb] > $microfecha){
					$oldId=$this->indice[$idB];
					$this->indice[$idB]=$microfecha;
					$this->asociated[$microfecha]=$this->asociated[$oldId];
					unset($this->asociated[$oldId]);
				}


				$this->asociated[$this->indice[$idB]][]=$idA;
				$this->indice[$idA]=$this->indice[$idB];
			}


		}else{
			//$this->uid++;
			$this->indice[$idA]=$microfecha;
			$this->indice[$idB]=$microfecha;
			$this->asociated[$microfecha][]=$idA;
			$this->asociated[$microfecha][]=$idB;

		}

	}


	public function getAsociated(){
		/*rsort($this->asociated);*/
		krsort($this->asociated);
		return $this->asociated;
	}

}

class nn_trunk{

	private $news=array();
	private $lastItem=-1;
	private $bestGroupNum=0;
	private $boxC;
	private $db=0;
	private $links=array();
	function __construct(){
		$this->boxC=new boxCompared();	
	}

	public function store($title, $content, $category, $link, $dbId, $origen, $fecha, $microfecha, $imgUrl=""){
		if(in_array($link, $this->links)){
			//echo "do nothing";
		}else{
			$this->links[]=$link;

			$this->news[]=new nn_noticia($title, $content, $category, $link, $dbId, $origen, $fecha, $microfecha, $imgUrl);
			$this->lastItem++; 								//Esto debe coincidir siempre con el ultimo key introducido en el array
			if($this->lastItem > 0){				//solo comparamos si hay 2 noticias minimo 0=1, 1=2

				$b=count($this->news)-1;
				for($i = 0; $i <= $b; $i++){
					if($i != $this->lastItem){ 					//evitamos compararla consigo misma
						$simil=$this->news[$this->lastItem]->comparateCon($this->news[$i], $this->lastItem, $i);
						if($simil > SIMILITUDMINIMA){
							//echo "\nDentro".date("j/n/Y",$this->news[$i]->getFecha())."\n";
							//echo "\nDentro".date("j/n/Y",$this->news[$this->lastItem]->getFecha())."\n";
							
							if($this->news[$this->lastItem]->getMicroFecha() > $this->news[$i]->getMicroFecha()){	//priorizamos el que tenga mayor fecha. Mejor al reves?
								$this->boxC->addSimil($this->lastItem, $i, $this->news[$i]->getMicroFecha());
							}else{
								$this->boxC->addSimil($this->lastItem, $i, $this->news[$this->lastItem]->getMicroFecha());
							}
						}
					}
				}
			}
		}
	}


	public function getNumOfNews(){
		return count($this->news());
	}

	public function getNewByNum($id){
		return $this->news[$id];
	}


	public function getImportants(){
		$asoci=$this->boxC->getAsociated();
		$nasoci=array();

		$b=count($asoci)-1;
		//for($i = 0; $i <= $b; $i++){
		foreach($asoci as $asocib){
			$tmp=array();
			$origen=array();
			foreach($asocib as $notice){
				if(in_array($this->news[$notice]->getOrigen(), $origen)){
					//echo "\nCDo nothing\n";
				}else{
					$tmp[]=$this->news[$notice];
					$origen[]=$this->news[$notice]->getOrigen();
				}
			}
			if(count($tmp) > 1){
				$nasoci[]=$tmp;
			}

		}
		//rsort($nasoci);
		$this->insertInDb($nasoci);
		return $nasoci;
	}


	public function insertInDb($nasoci){
		$dba=new db();
		foreach($nasoci as $block){
			$bblock=$block;
			foreach($block as $nNoti){
				foreach($bblock as $bnNoti){
					if($nNoti->getdbId() != $bnNoti->getdbId()){
						$a=$nNoti->getdbId();
						$b=$bnNoti->getdbId();
						echo "Consultando $a $b\n";
						$r=$dba->consulta("select a,b from similares where a=$a and b=$b");
						if($r == 0){
							echo "insertando $a $b\n";
							$dba->consulta("insert into similares (a,b) values('$a','$b')");
						}
						echo $nNoti->getdbId()." ".$bnNoti->getdbId()."\n";
					}
				}
			}
		}
		$dba->desconectar();
	}



	public function get_nn(){
		return $this->news;
	}

	public function get_nn_old(){
		print_r($this->ordenSimilares);
		$noticiasToReturn=array();
		foreach($this->news as $oneNotice){
			if($oneNotice->getDupe() == 0 && $oneNotice->getProcesed() == 0){
				$tmp=array();
				$tmp[]=$oneNotice;
				foreach($oneNotice->getSimilNews() as $keyId => $value ){
					$this->news[$keyId]->setProcesed();
					$tmp[]=$this->news[$keyId];
					
				}
				$noticiasToReturn[]=$tmp;
			}
		}	
		print_r($noticiasToReturn);	
		return $noticiasToReturn;
	}

}

class nn_noticia{

	private $title=array();
	private $title2k=array();

	private $content=array();
	private $content2k=array();

	private $category;
	private $similitud=array();

	private $raw_content;
	private $raw_title;
	private $link;
	private $dbId;
	private $origen;
	private $fecha;
	private $microfecha;
	private $imgUrl;
	private $duplicada=0;


	private $procesed=0;
	private $published=0;

	public function setPublished(){
		$this->published=1;
	}
	public function getPublished(){
		return $this->published;
	}

	function __construct($title, $content, $category, $link, $dbId, $origen, $fecha, $microfecha, $imgUrl="") {
		$this->category=$category;
		$this->raw_content=$content;
		$this->raw_title=$title;
		$this->link=$link;
		$this->dbId=$dbId;
		$this->origen=$origen;
		$this->fecha=$fecha;
		$this->microfecha=$microfecha;
		$this->imgUrl=$imgUrl;

		$this->nn_explode($title, $content);
		//aqui debemos limpiar las letras muy comunes y crear otro array de 2 palabras. ¿de tres tambien quizas?
		
	}

	public function setProcesed(){ $this->procesed=1;}
	public function getProcesed(){ return $this->procesed;}

	public function getRawContent(){ return $this->raw_content;}
	public function getRawTitle(){ return $this->raw_title;}
	public function getLink(){ return $this->link;}
	public function getdbId(){ return $this->dbId;}
	public function getOrigen(){ return $this->origen;}
	public function getFecha(){ return $this->fecha;}
	public function getMicroFecha(){ return $this->microfecha;}
	public function getImgUrl(){ return $this->imgUrl;}
	public function getCategory(){ return $this->category;}

	private function nn_explode($title, $content){

		$this->title=explode(" ", $title);
		$this->content=explode(" ", $content);
		
		//construimos el titulo por conjuncion de 2 palabras
		$lastItem=-1;
		foreach($this->title as $palabra){
			$this->title2k[]=$palabra;
			if($lastItem > -1){
				$this->title2k[$lastItem].=$palabra;
			}
			$lastItem++;

		}

		//construimos el contenido por conjuncion de 2 palabras
		$lastItem=-1;
		foreach($this->content as $palabra){
			$this->content2k[]=$palabra;
			if($lastItem > -1){
				$this->content2k[$lastItem].=$palabra;
			}
			$lastItem++;
		}

		//reconstruimos content y title sin palabras comunes
		$content=str_replace(" el ", " ", $content);
		$content=str_replace(" la ", " ", $content);
		$content=str_replace(" a ", " ", $content);
		$content=str_replace(" o ", " ", $content);
		$content=str_replace(" en ", " ", $content);
		$content=str_replace(" de ", " ", $content);
		$content=str_replace(" del ", " ", $content);
		$content=str_replace(" con ", " ", $content);
		$content=str_replace(" que ", " ", $content);
		$content=str_replace(" y ", " ", $content);
		$content=str_replace(",", " ", $content);
		$content=str_replace("   ", " ", $content);
		$content=str_replace("  ", " ", $content);
		$this->content=explode(" ", $content);

		$title=str_replace(" el ", " ", $title);
		$title=str_replace(" la ", " ", $title);
		$title=str_replace(" a ", " ", $title);
		$title=str_replace(" o ", " ", $title);
		$title=str_replace(" en ", " ", $title);
		$title=str_replace(" de ", " ", $title);
		$title=str_replace(" del ", " ", $title);
		$title=str_replace(" con ", " ", $title);
		$title=str_replace(" que ", " ", $title);
		$title=str_replace(" y ", " ", $title);
		$title=str_replace(",", " ", $title);
		$title=str_replace("   ", " ", $title);
		$title=str_replace("  ", " ", $title);
		$this->title=explode(" ", $title);
	}

	public function getSimilitud($id){
		return $this->similitud[$id];
	}

	public function putSimilitud($id, $value){
		$this->similitud[$id]=$value;
	}
	
	public function comparateCon($oneNotice, $idTu, $idElOtro){
		if($this->link == $oneNotice->link){
			$this->duplicada=1;
			return 0;
		}elseif(  strlen($oneNotice->getRawContent()) < 121 || strlen($this->getRawContent()) < 121  ){
			return 0;

		}else{
			//echo "\n\nComparando: \n";
			//echo $this->link."\n";
			//echo $oneNotice->link."\n";
			/********* el titulo *******/
			$theRank=0;
			foreach($oneNotice->getTitle() as $keyword ){
				if(in_array($keyword, $this->title)){
					$theRank++;
					$theRank++;
				}
			}
			$title100=($theRank/($oneNotice->getTitleSize()+$this->getTitleSize()))*100; //porcentaje de coincidencias
			/******************************/
	
	
	
			/********** el contenido ******/
			if($this->getContentSize() > 2 && $oneNotice->getContentSize() > 2){
				$theRank=0;
				foreach($oneNotice->getContent() as $keyword ){
					if(in_array($keyword, $this->content)){
						$theRank++;
						$theRank++;
					}
				}
				if($oneNotice->getContentSize() > $this->getContentSize()){ 
					$totalP=$this->getContentSize(); 
				}else{
					$totalP=$oneNotice->getContentSize();
				}
				$content100=($theRank/($totalP*2))*100; //porcentaje de coincidencias con respecto a la noticia con menos palabras
			}else{
				$content100=0;	
			}
			/********************************/
			
	
	
			/************ el titulo 2k **********/
			$theRank=0;
			foreach($oneNotice->getTitle2k() as $keyword){
				if(in_array($keyword, $this->title2k)){
					$theRank++;
					$theRank++;
				}
			}
			$title1002k=($theRank/($oneNotice->getTitleSize2k()+$this->getTitleSize2k()))*100;
			/*************************************/
	
			
	
			/*********** el contenido 2k ***************/
			if($this->getContentSize2k() > 2 && $oneNotice->getContentSize2k() > 2 ){
				$theRank=0;
				foreach($oneNotice->getContent2k() as $keyword ){
					if(in_array($keyword, $this->content2k)){
						$theRank++;
						$theRank++;
					}
				}
				if($oneNotice->getContentSize2k() > $this->getContentSize2k()){
					$totalP=$this->getContentSize2k();
				}else{
					$totalP=$oneNotice->getContentSize2k();
				}
				$content1002k=($theRank/($totalP*2))*100;
			}else{
				$content1002k=0;
			}
			/********************************************/

			//$title100;		15 = 0.15
			//$title1002k;		35 = 0.35 // antes 30, 0.3
			//$content100;		15 = 0.15 // antes 20, 0.2
			//$content1002k;	35 = 0.35

			$ponderado=($title100*0.15) + ($title1002k*0.35) + ($content100*0.15) + ($content1002k*0.35);
			$ponderadoOld=($title100*0.1) + ($title1002k*0.3) + ($content100*0.2) + ($content1002k*0.4);
			//if($ponderado > 10 || $ponderadoOld > 10){
		//		echo "\nmayor de 10\n";
		//	}
			//echo "\n ".$ponderadoOld." ".$ponderado." ".$this->getRawTitle()." ".$this->getdbId()." ------ ".$oneNotice->getRawTitle()." ".$oneNotice->getdbId()."\n";

			//if($ponderado >= SIMILITUDMINIMA){
			//
			//	$this->putSimilitud($idElOtro, $ponderado);
			//	$oneNotice->putSimilitud($idTu, $ponderado);
			//}
			return $ponderado;
		}//fin de comparacion de links
	}//fin de funcion

	public function getContent(){ return $this->content; }
	public function getContent2k(){ return $this->content2k;}
	public function getContentSize(){ return count($this->content); }
	public function getContentSize2k(){ return count($this->content2k);}

	public function getTitle(){ return $this->title; }
	public function getTitle2k(){ return $this->title2k; }
	public function getTitleSize(){ return count($this->title); }
	public function getTitleSize2k(){ return count($this->title2k);}

	public function getDupe(){
		return $this->duplicada;
	}

	public function getNumSimilares(){
		return count($this->similitud);
	}

	public function getSimilNews(){
		return $this->similitud;
	}

}






?>
