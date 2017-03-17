<?php

/**

 * Bulk Password Reset Module

 *

 * This module will reset all the hosting password and clients password


 *


 * @author     Daniel Babatunde forked from

 * @copyright  Copyright (c) Babatunde 2017

 * @license    http://www.whmcs.com/license/ WHMCS Eula

 * @version    $Id$

 * @link       http://www.whmcs.com/

 * @compatible WHMCS > 7.0

 */

set_time_limit(3600);


if (!defined("WHMCS"))

	die("This file cannot be accessed directly");



function bulk_password_reset_config() {
    $configarray = array(

    "name" => "Bulk Password Reset Module",

    "description" => "This module will reset all the hosting password and clients password.",

    "version" => "1.0",

    "author" => "Daniel Babatunde",

    "language" => "english",

    "fields" => array(
        "adminuser" => array ("FriendlyName" => "Admin Username", "Type" => "text", "Size" => "25", "Description" => "", "Default" => "", ),
        "resetclient" => array ("FriendlyName" => "Reset Client's Password?", "Type" => "yesno", "Size" => "25", "Description" => "Reset Client's Password", ),
        "resetproduct" => array ("FriendlyName" => "Reset Product's Password?", "Type" => "yesno", "Size" => "25", "Description" => "Reset Product's Password", ),
        "enable" => array ("FriendlyName" => "Enable", "Type" => "yesno", "Size" => "25", "Description" => "Enable", ),

    ));

    return $configarray;

}


function bulk_password_reset_output($vars) {

    $adminuser = $vars['adminuser'];
    if($vars['enable'] === 'on'){

        if(isset($_POST['reset_selected'])){
            print_r($_POST);
            $clients = $_POST['client'];
            clients_password_reset($adminuser, $clients);
        }elseif(isset($_POST['reset_all'])){
            if($vars['enable'] === 'on'){
                if($vars['resetclient'] === 'on'){
                    clients_password_reset($adminuser);
                }
                if($vars['resetproduct'] === 'on'){
                    products_password_reset($adminuser);
                }
            }
        }else{
            // Define parameters
            $command = 'GetClients';

            $postData = array(
                //'search' => 'example.com',
            );
            $adminUsername = $adminuser;

            $results = localAPI($command, $adminUsername);
            //print_r($results);
            if($results['result'] == 'success'){
                echo "<form method='post' action='".$vars['modulelink']."'> <div class=\"row client-dropdown-container\">";
                echo "<select id='select-clients' name='client[]' class='col-md-6' placeholder=\"Select a client...\" multiple>";
                foreach ($results['clients']['client'] as $client){
                    echo "<option value='".$client['id']."'>".$client['lastname']." ".$client['email']."</option>";
                }
                echo "</select>";
                echo "<input class='btn btn-primary' name='reset_selected' type='submit' value='Reset selected' />";
                echo '<button class="btn btn-info"> Reset for all</button>';
                echo '</div></form>';
                echo "<script>
				$('#select-clients').selectize({
                    delimiter: ',',
                    persist: false,
                    create: function(input) {
                        return {
                            value: input,
                            text: input
                        }
                    }
				});
				</script>";
            }

        }
    }
    else{
        echo 'Please enable the plugin.';
    }

}


function clients_password_reset($adminuser, array $clients = null){
    $val_from_args = false;
    if($clients !== null){
        $val_from_args = true;
    }else{
        $command = "getclients";
        $values['limitnum'] = 10000;
        $api_clients = localAPI($command,$values,$adminuser);

        if(!empty($api_clients['clients']['client'])){
            $clients = $api_clients['clients']['client'];
        }
        else{
            return false;
        }
    }


    if( !empty($clients) && is_array($clients)){

        foreach ($clients as $client) {
            $pw_command = "updateclient";

            if($val_from_args){
                $pw_values["clientid"] = $client;
            }else{
                $pw_values["clientid"] = $client['id'];
            }


            $pw_values["password2"] = generate_password(10);
            $pw_results = localAPI($pw_command, $pw_values, $adminuser);
            
            echo 'Reset password for: ' . $client['email'];
            if($val_from_args){
                echo 'Reset password for: ' . $client;
            }else{
                echo 'Reset password for: ' . $client['email'];
            }

            if(!empty($pw_results['result'])){
                if($pw_results['result'] === 'success'){
                    echo ' Success <br>';

                    $email_command = "sendemail";
                    //$email_values["messagename"] = "Automated Password Reset";
                    $email_values["messagename"] = "Automated Password Reset by Admin";
                    $email_values["id"] = $client['id'];
                     
                    $email_results = localAPI($email_command, $email_values, $adminuser);
                }
                else{
                    echo ' Failed ('.$pw_results['result'].') <br>';
                }
            }

        }

    }

}


function products_password_reset($adminuser, array $products = []){

    $command = "getclientsproducts";

    $api_services = localAPI($command,$values,$adminuser);

    if(!empty($api_services['products']['product'])){
        $products = $api_services['products']['product'];
    }
    else{
        return false;
    }

    if( !empty($products) && is_array($products)){

        foreach ($products as $product) {
             
             if($product['status'] === 'Active'){
                
                $pw_command = "modulechangepw";
                $pw_values["serviceid"] = $product['id'];
                $pw_values["servicepassword"] = generate_password();
                 
                $pw_results = localAPI($pw_command, $pw_values, $adminuser);
                
                echo 'Reset password for: ' . $product['domain'];

                if(!empty($pw_results['result'])){
                    if($pw_results['result'] === 'success'){
                        echo ' Success <br>';

                        $email_command = "sendemail";
                        $email_values["messagename"] = "Hosting Account Welcome Email";
                        $email_values["id"] = $product['id'];
                         
                        $email_results = localAPI($email_command, $email_values, $adminuser);
                    }
                    else{
                        echo ' Failed ('.$pw_results['result'].') <br>';
                    }
                }

             }
        }

    }

}

function generate_password($length = 15, $add_dashes = false, $available_sets = 'luds')
{
    $sets = array();
   
    if(strpos($available_sets, 'l') !== false)
        $sets[] = 'abcdefghjkmnpqrstuvwxyz';

    if(strpos($available_sets, 'u') !== false)
        $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';

    if(strpos($available_sets, 'd') !== false)
        $sets[] = '23456789';

    if(strpos($available_sets, 's') !== false)
        $sets[] = '!@#$%&*?';
     
    $all = '';
    $password = '';

    foreach($sets as $set)
    {
        $password .= $set[array_rand(str_split($set))];
        $all .= $set;
    }
     
    $all = str_split($all);

    for($i = 0; $i < $length - count($sets); $i++)
        $password .= $all[array_rand($all)];
     
    $password = str_shuffle($password);
     
    if(!$add_dashes)
        return $password;
     
    $dash_len = floor(sqrt($length));
    $dash_str = '';

    while(strlen($password) > $dash_len)
    {
        $dash_str .= substr($password, 0, $dash_len) . '-';
        $password = substr($password, $dash_len);
    }

    $dash_str .= $password;
    return $dash_str;
}