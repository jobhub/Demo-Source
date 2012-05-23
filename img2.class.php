<?php
class img{
    var $img_object;//ссылка на картинку
    var $sizeX=1000;
    var $gl=array(1,3);
    var $sizeY=300;
    var $days=10;
	var $start=0;
    var $startXline=0;
    var $startYline=0;
    var $margin_left=20;
    var $margin_right=0;
    var $margin_top=1;
    var $margin_bottom=120;
    var $padding_left=0;
    var $padding_top=0;
    var $data=array();
	var $max=0;
	var $img_cache_url='';
	var $id='';
    public function __construct($args=array()) {
        foreach($args as $key=>$val){
           if(isset($this->{$key})) $this->{$key}=$val;
           else $error=new error('arg '.$key.' not found');
        }
		$this->max=$this->data['max'];
		
		$this->data=$this->data['val'];
        $this->startYline=0;
        $this->startXline=$this->margin_left;
        $this->img_object = ImageCreate($this->sizeX+$this->margin_left+$this->margin_right, $this->sizeY+$this->margin_bottom+$this->margin_top);
        $this->padding_left=round($this->sizeX/$this->days);
        $this->padding_top=round(($this->sizeY)/$this->max);
		//var_dump($this->padding_top);
		//var_dump($this->data);
		//echo $this->max;
		//die();
        $background_color = imagecolorallocate($this->img_object, 230, 225, 205);

        $this->drawSet();
		imagesetthickness($this->img_object, 2);
        $this->drawText();
        foreach($this->gl as $key=>$value){
            $this->drawGraphic($this->gl[$key]);
            $this->startYline=0;
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
		if($par==1) $c=ImageColorAllocate($this->img_object, 200, 100, 100);
		if($par==3) $c=ImageColorAllocate($this->img_object, 100, 100, 200);
		//echo 123;
		$j=0;
		for($i=$this->start;$i<$this->days+$this->start;$i++){
			if(isset($this->data[$i][$par]['cnt']) AND @$this->data[$i][$par]['cnt_all']!=0){
				//$y=$this->sizeY-ceil($this->data[$i][$par]['cnt']*100/$this->data[$i][$par]['cnt_all'])*$this->sizeY/100;
				//$y=100;
				$y=$this->padding_top*$this->data[$i][$par]['cnt']/$this->data[$i][$par]['cnt_all'];
				$this->drawLine($this->padding_left*$j+$this->margin_left,$y,$c);
			}
			else{
				$this->drawLine($this->startXline,$this->startYline,$c);
			}
		$j++;
		}

    }
  /*  private function drawGraphic($par){
		if($par==1) $c=ImageColorAllocate($this->img_object, 200, 100, 100);
		if($par==3) $c=ImageColorAllocate($this->img_object, 100, 100, 200);
		//echo 123;
		$j=0;
		for($i=$this->start;$i<$this->days+$this->start;$i++){
			if(isset($this->data[$i][$par]['cnt']) AND @$this->data[$i][$par]['cnt_all']!=0){
				$y=$this->sizeY-ceil($this->data[$i][$par]['cnt']*100/$this->data[$i][$par]['cnt_all'])*$this->sizeY/100;
				$this->drawLine($this->padding_left*$j+$this->margin_left,$y,$c);
			}
			else{
				$this->drawLine($this->startXline,$this->startYline,$c);
			}
		$j++;
		}

    }
	*/
    private function drawText(){
        $setcol2 = ImageColorAllocate($this->img_object, 200, 100, 100);
        $setcol3 = ImageColorAllocate($this->img_object, 100, 100, 200);
		$j=0;$a=0;
		$cnt=count($this->data);
		for($i=$this->start;$i<$this->days+$this->start;$i++){
			if(isset($this->data[$i]['day'])){
				if($this->days<=60 OR $i%ceil($this->days/10)==0 OR $a>$this->days/40){
					ImageStringUp ($this->img_object,2,  $this->padding_left*$j+$this->margin_left-7, $this->sizeY+100, $this->data[$i]['day'], $setcol3);
					$a=0;
				}

			}
			$j++; 
			$a++;
		}
		
		//ImageString ($this->img_object, 11, 4, 0, 1, $setcol2);
		$coef=$this->max/15;
        for($i=0;$i<$this->max;$i++){
			if($i%$coef==0) ImageString ($this->img_object, 11, 4, $i*$this->padding_top, $i+1, $setcol2);
        }
    }
    private function drawSet(){
        //цвет сетки
        $setcol = ImageColorAllocate($this->img_object, 200, 200, 200);
        //вертикальные линии
        for($i=0;$i<$this->days;$i++){
            ImageLine($this->img_object, $this->padding_left*$i+$this->margin_left, $this->margin_top, $this->padding_left*$i+$this->margin_left, $this->sizeY+$this->margin_top, $setcol);
        }
        //горизонтальные линии
        for($i=0;$i<$this->max;$i++){
            ImageLine($this->img_object,$this->margin_left, $this->padding_top*($i)+$this->margin_top, $this->sizeX+$this->margin_left, $this->padding_top*($i)+$this->margin_top , $setcol);
        }
    }

    private function drawLine($x,$y,$c){
        ImageLine($this->img_object,$this->startXline, $this->startYline, $x, $y , $c);
        $this->startXline=$x;
        $this->startYline=$y;
    }
}
?>
