<?php
@include_once("../config.php");
/**
 * Created by JetBrains PhpStorm.
 * User: dlitvin
 * Date: 27.10.11
 * Time: 11:27
 * To change this template use File | Settings | File Templates.
 */

class query {

    protected  $cities     = array();
    protected  $cities_cut = array();


    /**
     *
     * Почистить массив запросов. Удалить повторяющиеся и те которые не схавает яндекс директ.
     *
     * @param array $queries исходный массив запросов
     *
     * @return array фильтрованный массив запросов
     *
     */
    public function filterQueries(array $queries){
        $res = array();
        foreach($queries as $q):
            $tt = preg_split('#\s+#', $q);
            if(count($tt) > 7)
                continue;
            if (preg_match_all('#[^а-яА-ЯёЁa-zA-Z0-9\s+\-\.]#isu',$q, $ttt )):
                //var_dump($q);
                continue;
            endif;

            if(false !== strpos($q, '+'))
                continue;

            $res[] = $q;
        endforeach;
        return array_unique($res);
    }

    /**
     * Удалить региональные запросы
     *
     *
     * @throws Exception
     * @param array $queries запросы для удаления региональных
     * @return array фильтрованные запросы
     */
    public function filterRegionalQueries(array $queries){

        if(empty($queries))
             throw new Exception('Нет запросов!');


        $result = array();
        $this->loadCities();
        $queries = $this->filterQueries($queries);

        foreach ($queries as $query):
            $qq = $this->compareQuery($query);
            if (!$qq):
                $result[] = $query;
            endif;
        endforeach;

        return $result;

    }//public function filterCities(array $queries){


    private function compareQuery($query){

        foreach ($this->cities as $city):
            if(preg_match('#'.$city.'#is', $query)):
                echo $city .' => '.$query.'<br>';
                return true;
            endif;
        endforeach;

        $q = preg_split('#\s+#', $query);
        /* паршивая привычка делатЬ всё и сразу. да, я понимаю что удобно. я понимаю что больше это нигде не используется. зато нифига непонятно*/
        foreach ($q as $word)://получить слова
        //тут все сложно. напо привести к одному виду разные города. из двух слов, из двух слов с тире, с пробелом перед тире ну и на что фантазии хватит
        // порезать у них окончания, потом пройтись по таким же обрезанным городам из базы и сравнить.
            if (preg_match('#-#', $word)):// слова с тире
                // в яндекс какой только жажи не вбивают.
                $word = preg_split('#-#', $word);
                $word_cut = '';
                foreach ($word as $c):// тут надо решить вопрос сколько букв считать за окончание и их обрезать
                    $length = mb_strlen($c, 'utf-8');

                    if($length >= 6)//тут типа две. цифры взяты с потолка и чуть подогнаны по результатам эксперимента
                        $cut = 2;
                    else// а тут одна
                        $cut = 1;

                    $word_cut .= mb_substr($c, 0, $length - $cut, 'utf-8');// обрезали, ну и склеиваем обратно через пробел
                    $word_cut .= ' ';// через пробел
                endforeach;//foreach ($word as $c):
                $word = trim($word_cut,'-');
            endif;// if (preg_match('#-#', $word)):

            // ну как то что то получили. можно сравнивать.

            $word = mb_convert_case($word, MB_CASE_LOWER, 'utf-8');
            foreach($this->cities_cut as $city): // если найдем город без окончания

                if(mb_strlen($city, 'utf-8') >= 4):
                if (preg_match('#^'.$city.'#', $word)):
                    echo $city .' => '.$word.'<br>';
                    return true;
                endif;
                endif;
            endforeach;//foreach($this->cities_cut as $city):

        endforeach;//foreach ($q as $word):

        return false;
    }// private function compareQuery($query){


    /**
     *
     * Загрузить массив городов из базы
     *
     * @throws Exception
     * @return bool
     */
    private  function loadCities(){
        if (!empty($this->cities))
            return true;


        $sql = "SELECT * FROM `cities`";
        $q   = db_query($sql);

        if(!mysql_num_rows($q))
            throw new Exception('В базе данных нет городов!');

        while (false !== ($query_result = mysql_fetch_array($q, MYSQL_ASSOC))):

            $this->cities[]     = trim($query_result['city']);
            $this->cities_cut[] = trim($query_result['city_cut']);

        endwhile;

        if (empty($this->cities) || empty($this->cities_cut))
            throw new Exception('Не удалось загрузить города!');

        return true;
    }


}
