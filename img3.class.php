<?php
class img{
    var $img_object;//ссылка на картинку
    var $sizeX=180;
    var $gl=array(1,3);
    var $sizeY=70;
    var $days=30;
	var $start=0;
    var $startXline=0;
    var $startYline=0;
    var $margin_left=20;
    var $margin_right=0;
    var $margin_top=2;
    var $margin_bottom=10;
    var $padding_left=0;
    var $padding_top=0;
    var $data=array();
	var $img_cache_url='';
	var $id='';
    public function __construct($args=array()) {
        foreach($args as $key=>$val){
           if(isset($this->{$key})) $this->{$key}=$val;
           else $error=new error('arg '.$key.' not found');
        }
		$this->img_cache_url=$_SERVER['DOCUMENT_ROOT'].'/img/'.date('Y-m-d');
        $this->startYline=$this ->sizeY;
        $this->startXline=$this->margin_left;
        $this->img_object = ImageCreate($this->sizeX+$this->margin_left+$this->margin_right, $this->sizeY+$this->margin_bottom+$this->margin_top);
        $this->padding_left=round($this->sizeX/$this->days);
        $this->padding_top=round($this->sizeY/10);
		//var_dump(count($this->data));

        $background_color = imagecolorallocate($this->img_object, 221, 230, 232);
        $this->drawSet();
		imagesetthickness($this->img_object, 1);
        $this->drawText();
        foreach($this->gl as $key=>$value){
            $this->drawGraphic($this->gl[$key]);
            $this->startYline=$this ->sizeY;
            $this->startXline=$this->margin_left;
        }
    }

    public function showImg(){
     header("Pragma: no-cache");
     header("Cache-control: private");
     header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
     header("Last-Modified:".gmdate("D, d M Y H:i:s")."GMT");
        $func='imagePng';
        header ('Content-Type: imagePng');
        $func($this->img_object);
    }
    private function drawGraphic($par){
		$cc=0;
		if($par==1){
			$c=ImageColorAllocate($this->img_object, 200, 100, 100);
			$cc=1;
		}
		if($par==3) $c=ImageColorAllocate($this->img_object, 100, 100, 200);
		//echo 123;
		$j=0;
		for($i=$this->start;$i<$this->days+$this->start;$i++){
		//echo $this->data[$i]['day'].'-'.$this->data[$i][$par]['cnt']*100/$this->data[$i][$par]['cnt_all'].'='.floatval($this->data[$i][$par]['cnt']*100).'/'.floatval($this->data[$i][$par]['cnt_all']).'<br/>';
			if(isset($this->data[$i][$par]['cnt']) AND @$this->data[$i][$par]['cnt_all']!=0 ){
				$y=$this->sizeY-ceil($this->data[$i][$par]['cnt']*100/$this->data[$i][$par]['cnt_all'])*$this->sizeY/100;
				$this->drawLine($this->padding_left*$j+$this->margin_left,$y+$cc,$c);
			}
			else{
				$this->drawLine($this->startXline,$this->startYline+$cc,$c);
			}
		$j++;
		}

    }
    private function drawText(){
        $setcol2 = ImageColorAllocate($this->img_object, 20, 10, 10);
        $setcol3 = ImageColorAllocate($this->img_object, 100, 100, 200);
		$j=0;$a=0;
		$cnt=count($this->data);
		for($i=$this->start;$i<$this->days+$this->start;$i++){
			if(isset($this->data[$i]['day'])){
				if($this->days<=5 OR $i%ceil($this->days/20)==0 OR $a>$this->days/19){
					ImageString ($this->img_object,1,  $this->padding_left*$j+$this->margin_left-5, $this->sizeY+1, substr($this->data[$i]['day'],8,2), $setcol3);
					$a=0;
				}
			}
		
			$j++; 
			$a++;
		}
		
		

        for($i=0;$i<11;$i+=2){
          ImageString ($this->img_object, 1, 4, $this->sizeY-($i)*$this->padding_top-1, $i*10, $setcol2);
        }
    }
    private function drawSet(){
        //цвет сетки
        $setcol = ImageColorAllocate($this->img_object, 250, 250, 250);
        //вертикальные линии
        for($i=0;$i<$this->days;$i++){
            ImageLine($this->img_object, $this->padding_left*$i+$this->margin_left, $this->margin_top, $this->padding_left*$i+$this->margin_left, $this->sizeY+$this->margin_top, $setcol);
        }
        //горизонтальные линии
        for($i=0;$i<10;$i++){
            ImageLine($this->img_object,$this->margin_left, $this->padding_top*($i)+$this->margin_top, $this->sizeX+$this->margin_left, $this->padding_top*($i)+$this->margin_top , $setcol);
        }
    }

    private function drawLine($x,$y,$c){
        ImageLine($this->img_object,$this->startXline, $this->startYline, $x, $y , $c);
        $this->startXline=$x;
        $this->startYline=$y;
    }
	public function saveImg(){
		 mkdir( $this->img_cache_url ,0777,true );
         $func='imagepng';
         $func($this->img_object,$this->img_cache_url.'/img_'.$this->id.'.png');
    }
}
?>
