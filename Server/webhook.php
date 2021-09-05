<?php
$profile = [["start" => microtime(true)]];
require '../vendor/autoload.php';

    $stable_coin = 'BUSD';
    ob_start();
    
    $csv_file = array();
    // Takes raw data from the request
    $json = file_get_contents('php://input');
    
    // Converts it into a PHP object
    $data = json_decode($json);
    $profile[] = ["get_json" => microtime(true)];
    
    //if(!isset($data->password) || $data->password!='bazinga')
       // die;
        
    $api = new Binance\API( "../api.json" );
    $profile[] = ["connect_api" => microtime(true)];

    date_default_timezone_set('Europe/Lisbon');
    echo date("Y-m-d"). ' - ' . date("h:i:sa") .PHP_EOL;
    echo "data   = ".print_r($data).PHP_EOL;
    $csv_file[0] = date("Y-m-d"). ' - ' . date("h:i:sa");
    
    $coin = str_replace($stable_coin, '', $data->ticker);
    //$balances = $api->balances();
    //echo "Balances = " . print_r($balances).PHP_EOL;
    if(file_get_contents('../balances_geofight.json')===FALSE)
    {
        echo "Error get from file balances_geofight.txt.";
        $get_balances = $api->balances();
        $balance[$stable_coin] = $get_balances[$stable_coin]['available'];
        $balance[$coin] = $get_balances[$coin]['available'];
    }
    else
    {
        $get_balances = file_get_contents('../balances_geofight.json');
        //echo "Balances = " . $get_balances . PHP_EOL;
        $balance = json_decode($get_balances, TRUE);        
    }
    $profile[] = ["get_balances" => microtime(true)];
    //echo "Balance = " . $balance[$coin] .' '. $coin . PHP_EOL;
    //echo "Ticker = " . $data->ticker . PHP_EOL;

    if($data->buy==1)
    {
        $d = $api->price($data->ticker);
        if(isset($balance[$stable_coin]) && $balance[$stable_coin]>0)
        {
            $quantity = number_format(($balance[$stable_coin]/$d), 3, '.', '');
            $quantity -= 0.005;
            echo "Quantity = " . $quantity . PHP_EOL;
            if($quantity > 0)
            {
                $profile[] = ["before_buy" => microtime(true)];
                $api->useServerTime();
                $order = $api->marketBuy($data->ticker, $quantity);
                $profile[] = ["after_buy" => microtime(true)];
                /*$balance[$stable_coin]=0;
                $balances = $api->balances();
                $balance[$coin]=$balances[$coin]['available'];*/
            }
            else
                echo "Not enough cash to make the buy".PHP_EOL;
           
        }
        else 
            echo (!isset($balance[$stable_coin])?$stable_coin ." isn't added into your balance.":$stable_coin ." has 0 balance.").PHP_EOL;
    }
    elseif($data->buy==0)
    {
        if(isset($balance[$coin]) && $balance[$coin] > 0)
        {
            $profile[] = ["before_sell" => microtime(true)];
            $api->useServerTime();
            $order = $api->marketSell($data->ticker, $balance[$coin]);
            $profile[] = ["after_sell" => microtime(true)];
            /*$balance[$coin]=0;
            $balances = $api->balances();
            $balance[$stable_coin]=$balances[$stable_coin]['available'];*/
        }
        else
        {
            if(!isset($balance[$coin]))
                echo "{$coin} isn't added into your balance".PHP_EOL;
            else    
                echo "Not enough {$coin} quantity to make the sell".PHP_EOL;      
        }
        
    }
    $csv_file[1] = $data->ticker;
    $csv_file[2] = $data->buy==1?"Buy":"Sell";
    if(isset($order))
    {

        echo "Order = " . $order['side'] .PHP_EOL;
        echo '<pre>'; print_r($order); echo '</pre>';
        $orderID = $order['clientOrderId'].PHP_EOL;
        $total = 0;
        $i = 0;
        foreach ($order['fills'] as $eachorder)
        {
           $total+=$eachorder['price'];
           $i++;
        }
        $csv_file[3] = $total/$i;
        $csv_file[4] = $order['executedQty'];
        $csv_file[5] = $order['cummulativeQuoteQty'];
    }

    
    

    
    $handle = fopen('../database_geofight.csv', "a");
    fputcsv($handle, $csv_file);
    fclose($handle);
    $balances = $api->balances();
    $balance[$coin]=$balances[$coin]['available'];
    $balance[$stable_coin]=$balances[$stable_coin]['available'];

    echo PHP_EOL.'***************************************************'.PHP_EOL;
    
    $htmlStr = ob_get_contents();
    ob_end_clean();
    $balance = json_encode($balance);
    if(file_put_contents('../db_geofight.txt', $htmlStr,FILE_APPEND | LOCK_EX)===FALSE)
        echo "Error writing to file db_geofight.txt.";
      
    if(file_put_contents('../balances_geofight.json', $balance,LOCK_EX)===FALSE)
        echo "Error writing to file balances_geofight.json.";
    
    $total =0;
    $a = date("Y-m-d"). ' - ' . date("h:i:sa") .PHP_EOL;
    isset($orderID)?$a .= 'Order ID = '.$orderID:"";
    $a .='
    <div>
    <ul>';
    $profile[] = ['end' => microtime(true)];
    for ($i = 0; $i < count($profile); $i++):
        $profile_entry = $profile[$i];
        $before_profile_entry = $profile[$i-1]??$profile_entry;
        $moment = array_keys($profile_entry)[0];
        $first_value = $profile_entry[$moment];
        $before_moment = array_keys($before_profile_entry)[0];
        $second_value = $before_profile_entry[$before_moment];
        $final_value = $first_value - $second_value;
        $total += $final_value;
        $a .= '<li>'. $moment .' : Elapsed:  '. $final_value.'</li>';
    endfor;
    $a .= '<li> TOTAL : Elapsed:  '. $total.'</li>
        </ul>
    </div>'.PHP_EOL;
    $a .= '**************************************'.PHP_EOL;
     if(file_put_contents('../log_geofight.txt', $a,FILE_APPEND | LOCK_EX)===FALSE)
         echo "Error copy to file.";
        ?>
    <div>
    <ul>
    <?php $profile[] = ['end' => microtime(true)];
    ?>
    <?php for ($i = 0; $i < count($profile); $i++):
      $profile_entry = $profile[$i];
      $before_profile_entry = $profile[$i-1]??$profile_entry;
      $moment = array_keys($profile_entry)[0];
      $first_value = $profile_entry[$moment];
      $before_moment = array_keys($before_profile_entry)[0];
      $second_value = $before_profile_entry[$before_moment];
      $final_value = $first_value - $second_value;
      
      ?>
   	<li><?= $moment ?>:<?= "Elapsed:  $final_value"; ?></li>
    <?php endfor; ?>
       <li> TOTAL : <?= "Elapsed:  $total"; ?></li>
      </ul>
    </div>

