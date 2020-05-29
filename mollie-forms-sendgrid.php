<?php
   /*
   Plugin Name: Mollie Forms Sendgrid Integration
   Plugin URI: https://kovio.com
   description: A Plugin to integrate Mollie Forms with Sendgrid Marketing
   Version: 1.0
   Author: Thomas de Bodt
   Author URI: https://www.linkedin.com/in/tdebodt/
   License: GPL2
   */

   defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

   require 'vendor/autoload.php';

   // Register the menu.
   add_action( "admin_menu", "mollie_forms_sendgrid_menu_func" );
   function mollie_forms_sendgrid_menu_func() {
      add_submenu_page( "options-general.php",  // Which menu parent
                  "Mollie Forms Sendgrid",            // Page title
                  "Mollie Forms Sendgrid",            // Menu title
                  "manage_options",       // Minimum capability (manage_options is an easy way to target administrators)
                  "mfs",            // Menu slug
                  "mfs_options"     // Callback that prints the markup
               );
   }

   add_action( 'admin_post_update_mfs_settings', 'mfs_handle_save' );
   add_action( 'admin_post_mfs_generate_list', 'mfs_generate_list' );


   function mfs_handle_save() {

   // Get the options that were sent
   $org = (!empty($_POST["gh_org"])) ? $_POST["gh_org"] : NULL;
   $repo = (!empty($_POST["gh_repo"])) ? $_POST["gh_repo"] : NULL;

   // Validation would go here

   // Update the values
   update_option( "mfs_sendgrid_apikey", $repo, TRUE );
   //update_option("mfs_", $org, TRUE);

   // Redirect back to settings page
   // The ?page=github corresponds to the "slug" 
   // set in the fourth parameter of add_submenu_page() above.
   $redirect_url = $_SERVER['HTTP_REFERER'] . "&status=success";
   header("Location: ".$redirect_url);
   exit;
   }

   // Print the markup for the page
   function mfs_options() {
    if ( !current_user_can( "manage_options" ) )  {
      wp_die( __( "You do not have sufficient permissions to access this page." ) );
    }

    if ( isset($_GET['status']) && $_GET['status']=='success') { ?>
   <div id="message" class="updated notice is-dismissible">
      <p><?php _e("Settings updated!", "github-api"); ?></p>
      <button type="button" class="notice-dismiss">
         <span class="screen-reader-text"><?php _e("Dismiss this notice.", "github-api"); ?></span>
      </button>
   </div> <?php
    }
  
    $apiKey = get_option('mfs_sendgrid_apikey');
    $sg = new \SendGrid($apiKey);


?>
    <form method="post" action="<?php echo admin_url( 'admin-post.php'); ?>">
      <input type="hidden" name="action" value="update_mfs_settings" />

   <h3><?php _e("Sendgrid Settings", "github-api"); ?></h3>
   <p>
   <label><?php _e("Sendgrid API Key:", "github-api"); ?></label>
   <input class="" style="width:50em;" type="password" name="gh_repo" value="<?php echo get_option('mfs_sendgrid_apikey'); ?>" />
   </p>

   <input class="button button-primary" type="submit" value="<?php _e("Save", "github-api"); ?>" />

</form>


<form method="post" action="<?php echo admin_url( 'admin-post.php'); ?>">

   <input type="hidden" name="action" value="mfs_generate_list" />

   <h3><?php _e("Mollie Monthly Subscription", "github-api"); ?></h3>
    <p>                                                                               
    <label><?php _e("Price Option:", "github-api"); ?></label>                      
       <select name="price_option_id" id="price_option_id">   
         <option value="4">Maandelijks Abonnement</option>   
       </select>                                         
   </p> 
             
   <p>                                                                                  
    <label><?php _e("Month:", "github-api"); ?></label>                                 
   <select name="month" id="month"><?php                                                
    for ($i=1; $i<=24; $i++) {                                                          
        echo '<option value="' . $i . '">' .  date("F Y", strtotime(($i-1) . " month ago")) . '</option>';                                                                       
    }                                                                                 
   ?></select>                                                        
   </p>
    
    <p>
    <label><?php _e("Sendgrid List:", "github-api"); ?></label>
   <select name="list_id" id="list_id" onchange="document.getElementById('list_name').disabled = false;if (this.value!='-1') { document.getElementById('list_name').disabled = true; }"><option value="0" selected="selected">[new]</option><?php
        $response = $sg->client->marketing()->lists()->get();
        $res = json_decode($response->body())->result;
        foreach ($res as $k=>$v){
            echo '<option value="' . $v->id .'">' . $v->name . '</option>'; // etc.
        }
   ?></select>
   </p>

   <p>
        <label><?php _e("Name of the New List:", "github-api"); ?></label>
        <input type="text" name="list_name" id="list_name"></input>
   </p>

   <p>
        <label>Test</label><input type="checkbox" id="test" name="test"></input>
   </p>
   <input class="button button-primary" type="submit" value="<?php _e("Go", "github-api"); ?>" />

</form>

    
<?php

    /*
    try {

        $listName = bin2hex(random_bytes(10));
        //echo $listName;                                                                                                                       
        $request_body = json_decode('{                                                                                                          
          "name": "' . bin2hex(random_bytes(10)) .'"                                                                                            
        }');
        //$response = $sg->client->marketing()->lists()->post($request_body);                                                                   
 
        $response = $sg->client->marketing()->lists()->get();
        $res = json_decode($response->body())->result;
        echo '<p><table class="widefat fixed"><thead><tr><th class="manage-column column-columnname">name</th></tr><tbody>';
        $alternate = $false;
        foreach ($res as $k=>$v){
            echo '<tr class="' . ($alternate?'alternate':'') . '"><td class="column-columnname">' . $v->name . '</td></tr>'; // etc.
            $alternate = !$alternate;
        }
        echo "</tbody></table></p>";

    } catch (Exception $e) {
        echo 'Exception reçue : ',  $e->getMessage(), "\n";
    }
    */
   }


function getSubscriptionContacts($price_option_id, $month) {
 try {
     $mollieForms = new \MollieForms\MollieForms();

     global $wpdb;
     $request = "SELECT 
          c.customer_id, 
          c.email, 
          (SELECT IF (value=c.email,NULL, value) from wp_mollie_forms_registration_fields where registration_id = r.id and field='Bevestig Email') as email_check, 
          SUBSTRING_INDEX(c.name,' ',1) AS first_name, 
          IF(POSITION(' ' in c.name)>0,SUBSTRING(c.name,POSITION(' ' in c.name)+1,LENGTH(c.name)),'') AS last_name 
          from " . $mollieForms->getPaymentsTable() . " p
LEFT JOIN " . $mollieForms->getRegistrationsTable() . " r ON p.registration_id = r.id
LEFT JOIN " . $mollieForms->getCustomersTable() . " c ON r.customer_id = c.customer_id
WHERE p.payment_mode = 'live'
AND p.payment_status = 'paid'
AND p.created_at BETWEEN CONVERT_TZ(CONVERT(DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL " . $month . " MONTH)), INTERVAL 1 DAY),DATETIME),'Europe/Brussels','UTC') AND  CONVERT_TZ(CONVERT(DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL " . ($month-1) . " MONTH)), INTERVAL 1 DAY),DATETIME),'Europe/Brussels','UTC')
AND p.registration_id in (
          SELECT registration_id from wp_mollie_forms_registration_price_options
          WHERE (price_option_id = " . $price_option_id . ")
          AND frequency <> 'once')";
     echo '<br/>' . $request;
     $res = $wpdb->get_results($request);
     //var_dump($res);
     return $res;
/*
     echo "<table><thead><tr><td>email</td><td>email_check</td><td>first_name</td><td>last_name</td></tr></thead><tbody>";
     foreach ($contacts as $k=>$v){
         echo "<tr><td>" . $v->email . "</td><td>" . $v->email_check . "</td><td>" . ucfirst($v->first_name) . "</td><td>" . $v->last_name . "</td></tr>";
         if ($v->email_check != NULL) {
           $redirect_url = $_SERVER['HTTP_REFERER'] . "&status=error&action=mfs_generate_list&customer_id=" . $v->customer_id;
           header("Location: ".$redirect_url);
           exit;
         }
     }
     echo "</tbody></table>";
     ¨*/
    } catch(Exception $e) {
        echo 'Exception reçue : ',  $e->getMessage(), "\n";
    }

 return NULL;

}


function mfs_generate_list() {
    var_dump($_POST);    
    $month = (!empty($_POST["month"])) ? intval($_POST["month"]) : NULL; 
    $price_option_id = (!empty($_POST["price_option_id"])) ? $_POST["price_option_id"] : NULL;
    $list_id = (!empty($_POST["list_id"])) ? $_POST["list_id"] : NULL;
    $list_name = (!empty($_POST["list_name"])) ? $_POST["list_name"] : NULL;
    $test = (!empty($_POST["test"])) ? $_POST["test"] : NULL;
    
    $apiKey = get_option('mfs_sendgrid_apikey');
    $sg = new \SendGrid($apiKey);
    
    $contacts = getSubscriptionContacts($price_option_id, $month);
    //var_dump($contacts);
    
    if ($test) {
     echo "<table><thead><tr><td>email</td><td>email_check</td><td>first_name</td><td>last_name</td></tr></thead><tbody>";
     foreach ($contacts as $k=>$v){
         echo "<tr><td>" . $v->email . "</td><td>" . $v->email_check . "</td><td>" . ucfirst($v->first_name) . "</td><td>" . $v->last_name . "</td></tr>";
         if ($v->email_check != NULL) {
           $redirect_url = $_SERVER['HTTP_REFERER'] . "&status=error&action=mfs_generate_list&customer_id=" . $v->customer_id;
           header("Location: ".$redirect_url);
           exit;
         }
     }
     echo "</tbody></table>";
     exit;
    }

    $listId = $list_id;
    if ($listId == NULL) {
//     $listName = "Abonnement Creatieve Dagboek " . date("F Y", strtotime(($month-1) . " month ago"));
      $listName = $list_name;
      echo "<p>Creating list " . $listName . "</p>";
      
       $request_body = json_decode('{
            "name": "' . $listName . '"
          }');
      $response = $sg->client->marketing()->lists()->post($request_body);
      $listId = json_decode($response->body())->id;    
      echo '<p>List created with id ' . $listId . ' and name ' . $listName . '</p>';
    }
    
    $request_body_json = '{
          "list_ids": ["' . $listId . '"],
          "contacts" : [';
    foreach ($contacts as $k=>$v) {
        $request_body_json .= '{"email":"' . $v->email . '", "first_name":"' . ucfirst($v->first_name) . '", "last_name":"' . $v->last_name . '"}';
        if (next($contacts)==true) $request_body_json .= ',';
    }
    $request_body_json .= ']}';
    echo $request_body_json;
    $request_body = json_decode($request_body_json);
    $response = $sg->client->marketing()->contacts()->put($request_body);
    echo '<p>' . $response->body() . '</p>';

   $redirect_url = $_SERVER['HTTP_REFERER'] . "&status=success&action=mfs_generate_list&list_id=" . $listId;
   //header("Location: ".$redirect_url);
   exit;

}

?>