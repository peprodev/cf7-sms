<?php
/*
Plugin Name: Pepro CF7 SMS Notifier
Description: Send SMS notifications to Users and Admins upon Contact Form 7 Submission
Contributors: amirhosseinhpv, peprodev
Tags: wordpress contact form, cf7 database, contact form 7, contact form 7 notifier, cf7 sms, contact form 7 sms
Author: Pepro Dev. Group
Developer: Amirhosseinhpv
Author URI: https://pepro.dev/
Developer URI: https://hpv.im/
Plugin URI: https://pepro.dev/cf7-database/
Version: 1.0.0
Stable tag: 1.0.0
Requires at least: 5.0
Tested up to: 5.4
Requires PHP: 5.6
WC requires at least: 4.0
WC tested up to: 4.2.0
Text Domain: cf7sms
Domain Path: /languages
Copyright: (c) 2020 Pepro Dev. Group, All rights reserved.
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
defined("ABSPATH") or die("CF7 Database :: Unauthorized Access!");
if (!class_exists("cf7Notifier")) {
    class cf7Notifier
    {
        private static $_instance = null;
        public $td;
        public $url;
        public $version;
        public $title;
        public $title_w;
        public $db_slug;
        private $plugin_dir;
        private $plugin_url;
        private $assets_url;
        private $plugin_basename;
        private $plugin_file;
        private $deactivateURI;
        private $deactivateICON;
        private $versionICON;
        private $authorICON;
        private $settingICON;
        private $db_table = null;
        private $manage_links = array();
        private $meta_links = array();
        /**
         * @method __construct
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function __construct()
        {
            global $wpdb;
            $this->td = "cf7sms";
            self::$_instance = $this;
            $this->db_slug = $this->td;
            $this->db_table = $wpdb->prefix . $this->db_slug;
            $this->plugin_dir = plugin_dir_path(__FILE__);
            $this->plugin_url = plugins_url("", __FILE__);
            $this->assets_url = plugins_url("/assets/", __FILE__);
            $this->plugin_basename = plugin_basename(__FILE__);
            $this->url = admin_url("admin.php?page={$this->db_slug}");
            $this->plugin_file = __FILE__;
            $this->version = "1.0.0";
            $this->deactivateURI = null;
            $this->deactivateICON = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-dismiss" aria-hidden="true"></span> ';
            $this->versionICON = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-admin-plugins" aria-hidden="true"></span> ';
            $this->authorICON = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-admin-users" aria-hidden="true"></span> ';
            $this->settingURL = '<span style="display: inline;float: none;padding: 0;" class="dashicons dashicons-admin-settings dashicons-small" aria-hidden="true"></span> ';
            $this->submitionURL = '<span style="display: inline;float: none;padding: 0;" class="dashicons dashicons-images-alt dashicons-small" aria-hidden="true"></span> ';
            $this->title = __("CF7 SMS Notifier", $this->td);
            $this->title_s = __("CF7 SMS", $this->td);
            $this->title_w = sprintf(__("%2\$s ver. %1\$s", $this->td), $this->version, $this->title);
            $this->APIKey = $this->read_opt("{$this->db_slug}-user_api_key");
            $this->SecretKey = $this->read_opt("{$this->db_slug}-user_secret_key");
            $this->APIURL = "https://ws.sms.ir/";
            $this->LineNumber = $this->read_opt("{$this->db_slug}-line_number");

            add_action("init", array($this, 'init_plugin'));

            add_action( "wpcf7_mail_sent",          array($this,  "cf7sms_before_send_mail_hook") );
            // add_action( "wpcf7_before_send_mail",   array($this,  "cf7sms_before_send_mail_hook") );
            add_filter( "wpcf7_editor_panels",      array($this,  "cf7sms_editor_panel"), 10, 1 );
            add_action( "wpcf7_after_save",         array($this,  "cf7sms_save_formdata") );

        }
        /**
         * Init Plugin
         *
         * @method init_plugin
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function init_plugin()
        {
            add_filter("plugin_action_links_{$this->plugin_basename}", array($this, 'plugins_row_links'));
            add_action("plugin_row_meta", array( $this, 'plugin_row_meta' ), 10, 2);
            add_action("admin_menu", array($this, 'admin_menu'));
            add_action("admin_init", array($this, 'admin_init'));
            add_action("admin_enqueue_scripts", array($this, 'admin_enqueue_scripts'));
            add_action("wp_ajax_nopriv_cf7sms_{$this->td}", array($this, 'handel_ajax_req'));
            add_action("wp_ajax_cf7sms_{$this->td}", array($this, 'handel_ajax_req'));
            $this->CreateDatabase(); // always check if table exist or not
        }
        /**
         * contact form 7 edit cf7 setting tabs hook
         *
         * @method cf7sms_editor_panel
         * @param array $panels
         * @return array $panels with added data
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function cf7sms_editor_panel($panels)
        {
          $panels['pepro_cf7sms'] = array(
            'title' => __("SMS Notification",$this->td),
            'callback' => array($this,'cf7sms_editor_panel_html')
          );
          return $panels;
        }
        /**
         * contact form 7 setting tab, callback html handler
         *
         * @method cf7sms_editor_panel_html
         * @param array $args
         * @return string html data of setting page
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function cf7sms_editor_panel_html($args)
        {
          $this->update_footer_info();
          $cf7sms = get_option( "pepro_cf7sms_{$args->id}" , array());
          wp_enqueue_style( $this->td, "{$this->assets_url}css/cf7_edit_screen.css");
          wp_enqueue_script( $this->td, "{$this->assets_url}js/cf7_edit_screen.js", array("jquery"));
          ?>
          <div class="pepro_cf7sms_panel">
            <h2><?=$this->title;?></h2>
            <div class="cf7sms_setting_section">
              <p>
                <input type="checkbox" class="cf7smscheckbox" id="pepro_cf7sms_active_sms_admin" name="pepro_cf7sms[active_sms_admin]" value="1" <?=checked(1,(isset($cf7sms['active_sms_admin']) ? $cf7sms['active_sms_admin']:"0"), false );?> />
                <label for="pepro_cf7sms_active_sms_admin"><?=__("Activate SMS Notification for Administrators",$this->td);?></label>
              </p>
              <p class="extra">
                <label for="pepro_cf7sms_admin_sms_mobile"><?=__("Administrators Mobile:",$this->td);?></label>
                <input type="text" id="pepro_cf7sms_admin_sms_mobile" name="pepro_cf7sms[admin_sms_mobile]" style="width: 100%;" placeholder="<?=esc_attr__("Enter Administrators mobiles number (Comma separated, with no spaces between)",$this->td);?>" title="<?=esc_attr__("Enter Administrators mobiles number (Comma separated, with no spaces between)",$this->td);?>" value="<?=(isset($cf7sms['admin_sms_mobile']) ? esc_attr( $cf7sms['admin_sms_mobile'] ):"");?>" />
              </p>
              <p class="extra">
                <label for="pepro_cf7sms_admin_sms_text"><?=__("SMS Body:",$this->td);?></label>
                <textarea id="pepro_cf7sms_admin_sms_text" name="pepro_cf7sms[admin_sms_text]" style="width: 100%;" row="8"
                  placeholder="<?=esc_attr__("Enter SMS text, you can use Contact Form 7 short tags just like what you've entered as Email body in Mail tab",$this->td);?>"
                  title="<?=esc_attr__("Enter SMS text, you can use Contact Form 7 short tags just like what you've entered as Email body in Mail tab",$this->td);?>" ><?=
                  (isset($cf7sms['admin_sms_text']) ? esc_attr( $cf7sms['admin_sms_text'] ):"");
                ?></textarea>
              </p>
              <p class="extra">
                <input type="checkbox" id="pepro_cf7sms_active_sms_admin_fast" name="pepro_cf7sms[active_sms_admin_fast]" value="1" <?=checked(1,(isset($cf7sms['active_sms_admin_fast']) ? $cf7sms['active_sms_admin_fast']:"0"), false );?> />
                <label for="pepro_cf7sms_active_sms_admin_fast"><?=__("Use Ultra-fast SMS sending feature",$this->td);?></label>
              </p>
              <p class="extra">
                <label for="pepro_cf7sms_active_sms_admin_fast_id"><?=__("Ultra-fast Template ID:",$this->td);?></label>
                <input type="text" id="pepro_cf7sms_active_sms_admin_fast_id" name="pepro_cf7sms[active_sms_admin_fastID]" style="width: 100%;"
                placeholder="<?=esc_attr__("Enter Ultra-fast Template ID",$this->td);?>"
                title="<?=esc_attr__("Enter Ultra-fast Template ID",$this->td);?>" value="<?=(isset($cf7sms['active_sms_admin_fastID']) ? esc_attr( $cf7sms['active_sms_admin_fastID'] ):"");?>" />
              </p>
              <p class="extra">
                <label for="pepro_cf7sms_active_sms_admin_fast_ids"><?=__("Ultra-fast Template Params:",$this->td);?></label>
                <input type="text" id="pepro_cf7sms_active_sms_admin_fast_ids" name="pepro_cf7sms[active_sms_admin_fastIDparam]" style="width: 100%;"
                placeholder="<?=esc_attr__("Enter Ultra-fast Template Parameters (Comma separated, with no spaces between)",$this->td);?>"
                title="<?=esc_attr__("Enter Ultra-fast Template Parameters (Comma separated, with no spaces between)",$this->td);?>" value="<?=(isset($cf7sms['active_sms_admin_fastIDparam']) ? esc_attr( $cf7sms['active_sms_admin_fastIDparam'] ):"");?>" />
              </p>
            </div>
            <div class="cf7sms_setting_section">
              <p>
                <input type="checkbox" class="cf7smscheckbox" id="pepro_cf7sms_active_sms_user" name="pepro_cf7sms[active_sms_user]" value="1" <?=checked(1,(isset($cf7sms['active_sms_user']) ? $cf7sms['active_sms_user']:"0"), false );?> />
                <label for="pepro_cf7sms_active_sms_user"><?=__("Activate SMS Notification for Submitter",$this->td);?></label>
              </p>
              <p class="extra">
                <label for="pepro_cf7sms_user_sms_mobile"><?=__("Submitter Mobile Field:",$this->td);?></label>
                <input type="text" id="pepro_cf7sms_user_sms_mobile" name="pepro_cf7sms[user_sms_mobile]" style="width: 100%;"
                placeholder="<?=esc_attr__("Enter the mobile field you set for submitter. e.g. your-phone",$this->td);?>"
                title="<?=esc_attr__("Enter the mobile field you set for submitter. e.g. your-phone",$this->td);?>" value="<?=(isset($cf7sms['user_sms_mobile']) ? esc_attr( $cf7sms['user_sms_mobile'] ):"");?>" />
              </p>
              <p class="extra">
                <label for="pepro_cf7sms_user_sms_text"><?=__("SMS Body:",$this->td);?></label>
                <textarea id="pepro_cf7sms_user_sms_text" name="pepro_cf7sms[user_sms_text]" style="width: 100%;" row="8"
                  placeholder="<?=esc_attr__("Enter SMS text, you can use Contact Form 7 short tags just like what you've entered as Email body in Mail tab",$this->td);?>"
                  title="<?=esc_attr__("Enter SMS text, you can use Contact Form 7 short tags just like what you've entered as Email body in Mail tab",$this->td);?>" ><?=
                  (isset($cf7sms['user_sms_text']) ? esc_attr( $cf7sms['user_sms_text'] ):"");
                ?></textarea>
              </p>
              <p class="extra">
                <input type="checkbox" id="pepro_cf7sms_active_sms_user_fast" name="pepro_cf7sms[active_sms_user_fast]" value="1" <?=checked(1,(isset($cf7sms['active_sms_user_fast']) ? $cf7sms['active_sms_user_fast']:"0"), false );?> />
                <label for="pepro_cf7sms_active_sms_user_fast"><?=__("Use Ultra-fast SMS sending feature",$this->td);?></label>
              </p>
              <p class="extra">
                <label for="pepro_cf7sms_active_sms_user_fast_id"><?=__("Ultra-fast Template ID:",$this->td);?></label>
                <input type="text" id="pepro_cf7sms_active_sms_user_fast_id" name="pepro_cf7sms[active_sms_user_fastID]" style="width: 100%;"
                placeholder="<?=esc_attr__("Enter Ultra-fast Template ID",$this->td);?>"
                title="<?=esc_attr__("Enter Ultra-fast Template ID",$this->td);?>" value="<?=(isset($cf7sms['active_sms_user_fastID']) ? esc_attr( $cf7sms['active_sms_user_fastID'] ):"");?>" />
              </p>
              <p class="extra">
                <label for="pepro_cf7sms_active_sms_user_fast_ids"><?=__("Ultra-fast Template Params:",$this->td);?></label>
                <input type="text" id="pepro_cf7sms_active_sms_user_fast_ids" name="pepro_cf7sms[active_sms_user_fastIDparam]" style="width: 100%;"
                placeholder="<?=esc_attr__("Enter Ultra-fast Template Parameters (Comma separated, with no spaces between)",$this->td);?>"
                title="<?=esc_attr__("Enter Ultra-fast Template Parameters (Comma separated, with no spaces between)",$this->td);?>" value="<?=(isset($cf7sms['active_sms_user_fastIDparam']) ? esc_attr( $cf7sms['active_sms_user_fastIDparam'] ):"");?>" />
              </p>
            </div>
          </div>
          <?php
        }
        /**
         * contact form 7 save custom setting tab's data
         *
         * @method cf7sms_save_formdata
         * @param array $args
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function cf7sms_save_formdata($args)
        {
          if (!empty($_POST)){
            update_option( "pepro_cf7sms_{$args->id}", $_POST['pepro_cf7sms'] );
          }
        }
        /**
         * hook to cf7 send sms
         *
         * @method cf7sms_before_send_mail_hook
         * @param array $form_to_DBs
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function cf7sms_before_send_mail_hook( $form_to_DBs )
        {
          $form_to_DB = WPCF7_Submission::get_instance();
          if ( $form_to_DB ) {
            $formData   = $form_to_DB->get_posted_data();
            $subject    = isset($formData['your-subject']) ? $formData['your-subject'] : "";
            $name       = isset($formData['your-name']) ? $formData['your-name'] : "";
            $email      = isset($formData['your-email']) ? $formData['your-email'] : "";
            $form_id    = $form_to_DBs->id();
            $cf7sms     = get_option( "pepro_cf7sms_{$form_id}" , array());
            $reciever   = isset($formData[$cf7sms["user_sms_mobile"]]) ? $formData[$cf7sms["user_sms_mobile"]] : (isset($formData['your-phone'])?$formData['your-phone']:"");

            $extra_info = "";foreach ($formData as $key => $value) {
              $extra_info .= "$key: $value\r\n";
            };

            $msgbodyAdmin = $cf7sms["admin_sms_text"];
            $msgbodyUser = $cf7sms["user_sms_text"];

            foreach ($formData as $key => $value) {
              $msgbodyAdmin = str_replace("[$key]", $value, $msgbodyAdmin);
              $msgbodyUser = str_replace("[$key]", $value, $msgbodyUser);
            }

            $contactform = "* {$form_to_DBs->title()} *";


            // check if ADMIN Notification is set to <ON>
            if (isset($cf7sms["active_sms_admin"]) && "1" == $cf7sms["active_sms_admin"]){
              $formData["_sentforadmin_or_user"] = "YES";
              // yes, now send sms to admin
              if (isset($cf7sms["admin_sms_mobile"]) && !empty($cf7sms["admin_sms_mobile"])){
                // admin mobile is okay | send ultrafast or normal?
                $MobileNumbers = explode(",",$cf7sms["admin_sms_mobile"]);
                $MobileNumbers = array_filter($MobileNumbers,function($var){return (int) trim($var);});
                if (isset($cf7sms["active_sms_admin_fast"]) && 1 == $cf7sms["active_sms_admin_fast"]){
                  // send ultrafast
                  if (isset($cf7sms["active_sms_admin_fastID"]) && !empty($cf7sms["active_sms_admin_fastID"])){
                    $msgbodyAdmin = "ULTRAFAST";
                    if (isset($cf7sms["active_sms_admin_fastIDparam"]) && !empty($cf7sms["active_sms_admin_fastIDparam"])){

                      $reqired_params = explode(",",$cf7sms["active_sms_admin_fastIDparam"]);
                      $reqired_params = array_filter($reqired_params,function($var){return trim($var);});
                      $ParameterArray = array();

                      foreach ($formData as $key => $value) {
                        if (in_array($key, $reqired_params)){
                          array_push($ParameterArray,array( "Parameter" => "$key", "ParameterValue" => "$value" ));
                        }
                      }
                      foreach ($MobileNumbers as $ue) {
                        $status = $this->send_ultrafast_sms(
                          array(
                            "ParameterArray" => $ParameterArray,
                            "Mobile" => $ue,
                            "TemplateId" => $cf7sms["active_sms_user_fastID"]
                          )
                        );
                      }
                      if ("your verification code is sent" == $status){
                        $status = 100;
                      }
                    }else{
                      $status = __("Ultra-fast Template Params missing.",$this->td);
                    }
                  }else{
                    $status = __("Ultra-fast Template ID missing.",$this->td);
                  }
                }
                else{
                    // ultrafast is not set or it's Template ID is missing, so let's send normal SMS to admin numbers
                    $status = $this->send_normal_sms($MobileNumbers,array($msgbodyAdmin));
                  }
                $this->save_submition($form_id, implode(", ", $MobileNumbers), ($status===true?100:$status), $msgbodyAdmin, serialize($formData));
              }
              else{
                // admin mobile is not set
                $this->save_submition($form_id, __("No admin mobile is set to recieve SMS",$this->td), "400", $msgbodyAdmin, serialize($formData));
              }
            }

            // check if user Notification is set to <ON>
            if (isset($cf7sms["active_sms_user"]) && "1" == $cf7sms["active_sms_user"]){
              $formData["_sentforadmin_or_user"] = "NO";
              // yes, now send sms to user
              if (isset($cf7sms["user_sms_mobile"]) && !empty($cf7sms["user_sms_mobile"])){
                // user mobile is okay | send ultrafast or normal?
                $MobileNumbers = (int) trim($formData[$cf7sms["user_sms_mobile"]]);
                if (isset($cf7sms["active_sms_user_fast"]) && 1 == $cf7sms["active_sms_user_fast"]){
                  // send ultrafast
                  if (isset($cf7sms["active_sms_user_fastID"]) && !empty($cf7sms["active_sms_user_fastID"])){
                    $msgbodyUser = "ULTRAFAST";
                    if (isset($cf7sms["active_sms_user_fastIDparam"]) && !empty($cf7sms["active_sms_user_fastIDparam"])){

                      $reqired_params = explode(",",$cf7sms["active_sms_user_fastIDparam"]);
                      $reqired_params = array_filter($reqired_params,function($var){return trim($var);});
                      $ParameterArray = array();

                      foreach ($formData as $key => $value) {
                        if (in_array($key, $reqired_params)){
                          array_push($ParameterArray,array( "Parameter" => "$key", "ParameterValue" => "$value" ));
                        }
                      }
                      $status = $this->send_ultrafast_sms(
                        array(
                          "ParameterArray" => $ParameterArray,
                          "Mobile" => $MobileNumbers,
                          "TemplateId" => $cf7sms["active_sms_user_fastID"]
                        )
                      );
                      if ("your verification code is sent" == $status){
                        $status = 100;
                      }
                    }else{
                      $status = __("Ultra-fast Template Params missing.",$this->td);
                    }
                  }else{
                    $status = __("Ultra-fast Template ID missing.",$this->td);
                  }
                }
                else{
                    // ultrafast is not set or it's Template ID is missing, so let's send normal SMS to user numbers
                    $status = $this->send_normal_sms(array($MobileNumbers),array($msgbodyUser));
                  }
                $this->save_submition($form_id, implode(", ", (array) $MobileNumbers), ($status===true?100:$status), $msgbodyUser, serialize($formData));
              }
              else{
                // user mobile is not set
                $this->save_submition($form_id, __("No user mobile is set to recieve SMS",$this->td), "400", $msgbodyUser, serialize($formData));
              }
            }


          }

          return $form_to_DBs;
        }
        /**
         * Get Plugin Setting Options
         *
         * @method get_setting_options
         * @return array plugin settings
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function get_setting_options()
        {
          return array(
            array(
              "name" => "{$this->db_slug}_general",
              "data" => array(
                "{$this->db_slug}-clearunistall" => "no",
                "{$this->db_slug}-cleardbunistall" => "no",
                "{$this->db_slug}-user_secret_key" => "",
                "{$this->db_slug}-user_api_key" => "",
                "{$this->db_slug}-line_number" => "30002101000338",
              )
            ),
          );
        }
        /**
         * wp get_meta_link hool
         *
         * @method get_meta_links
         * @return array meta_link
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function get_meta_links()
        {
            if (!empty($this->meta_links)) {return $this->meta_links;
            }
            $this->meta_links = array(
                  'support'      => array(
                      'title'       => __('Support', $this->td),
                      'description' => __('Support', $this->td),
                      'icon'        => 'dashicons-admin-site',
                      'target'      => '_blank',
                      'url'         => "mailto:support@pepro.dev?subject={$this->title}",
                  ),
              );
            return $this->meta_links;
        }
        /**
         * wp get_manage_links hool
         *
         * @method get_manage_links
         * @return array get_manage_links
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function get_manage_links()
        {
            if (!empty($this->manage_links)) {return $this->manage_links;
            }
            $this->manage_links = array(
              $this->settingURL . __("Settings", $this->td) => $this->url,
              $this->submitionURL . __("Sent SMS Log", $this->td) => $this->url,
            );
            return $this->manage_links;
        }
        /**
         * Activation Hook
         *
         * @method activation_hook
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public static function activation_hook()
        {
            (new cf7Notifier)->CreateDatabase();
        }
        /**
         * Deactivation Hook
         *
         * @method deactivation_hook
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public static function deactivation_hook()
        {
        }
        /**
         * Uninstall Hook
         *
         * @method uninstall_hook
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public static function uninstall_hook()
        {
            $ppa = new cf7Notifier;
            $dbClear = get_option("{$ppa->db_slug}-cleardbunistall", "no") === "yes" ? $ppa->DropDatabase() : null;
            if (get_option("{$ppa->db_slug}-clearunistall", "no") === "yes") {
                $cf7Notifier_class_options = $ppa->get_setting_options();
                foreach ($cf7Notifier_class_options as $options) {
                    $opparent = $options["name"];
                    foreach ($options["data"] as $optname => $optvalue) {
                        unregister_setting($opparent, $optname);
                        delete_option($optname);
                    }
                }
            }
        }
        /**
         * Create Database Scheme
         *
         * @method CreateDatabase
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function CreateDatabase()
        {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();
            $tbl = $this->db_table;
            if ($wpdb->get_var("SHOW TABLES LIKE '". $tbl ."'") != $tbl ) {
                $sql = "CREATE TABLE `$tbl` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `date_created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `form_id` VARCHAR(512),
            `reciever` VARCHAR(320),
            `status` VARCHAR(320),
            `msgbody` TEXT,
            `extra_info` TEXT,
            PRIMARY KEY id (id)
          ) $charset_collate;";
                if(!function_exists('dbDelta')) {include_once ABSPATH . 'wp-admin/includes/upgrade.php';
                }
                dbDelta($sql);
                // error_log("$tbl Created");
            }else{
                // error_log("$tbl Already Exist");
            }
        }
        /**
         * Clear Database
         *
         * @method DropDatabase
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function DropDatabase()
        {
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS {$this->db_table}");
        }
        /**
         * Update Footer Info to Developer info
         *
         * @method update_footer_info
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        private function update_footer_info()
        {
            add_filter(
                'admin_footer_text', function () {
                    return sprintf(_x("Thanks for using %s products", "footer-copyright", $this->td), "<b><a href='https://pepro.dev/' target='_blank' >".__("Pepro Dev", $this->td)."</a></b>");
                }, 11
            );
            add_filter(
                'update_footer', function () {
                    return sprintf(_x("%s — Version %s", "footer-copyright", $this->td), $this->title, $this->version);
                }, 11
            );
            echo "<style>
            #footer-left b a::before {
            	content: '';
            	background: url('{$this->assets_url}images/peprodev.svg') no-repeat;
            	background-position-x: center;
            	background-position-y: center;
            	background-size: contain;
            	width: 60px;
            	height: 40px;
              display: inline-block;
              pointer-events: none;
            	position: absolute;
            	-webkit-margin-before: calc(-60px + 1rem);
            	        margin-block-start: calc(-60px + 1rem);
            	-webkit-filter: opacity(0.0);
            	filter: opacity(0.0);
            	transition: all 0.3s ease-in-out;
            }
            #footer-left b a:hover::before {
            	-webkit-filter: opacity(1.0);
            	filter: opacity(1.0);
            	transition: all 0.3s ease-in-out;
            }
            </style>";
        }
        /**
         * callback for ajax requesets
         *
         * @method handel_ajax_req
         * @return json ajax response
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function handel_ajax_req()
        {
            if (wp_doing_ajax() && $_POST['action'] == "cf7sms_{$this->td}") {

                if (!wp_verify_nonce( $_POST["nonce"], $this->td)){

                  wp_send_json_error( array("msg"=>__("Unauthorized Access!",$this->td)));

                }

                if (isset($_POST["wparam"]) && "delete_item" == trim($_POST["wparam"]) && !empty($_POST["lparam"])) {

                  global $wpdb;
                  $id = (int) trim($_POST["lparam"]);
                  $del = $wpdb->delete( $this->db_table , array( 'ID' => $id ) );
                  if (false !== $del){
                    wp_send_json_success( array( "msg" => sprintf(__("Submition ID %s Successfully Deleted.",$this->td),$id ) ) );
                  }else{
                    wp_send_json_error( array( "msg" => sprintf(__("Error Deleting Submition ID %s.",$this->td),$id ) ) );
                  }

                }

                if (isset($_POST["wparam"]) && "clear_db_cf7" == trim($_POST["wparam"]) && !empty($_POST["lparam"])) {

                  global $wpdb;
                  $id = (int) trim($_POST["lparam"]);
                  $del = $wpdb->delete( $this->db_table , array( 'extra_info' => $id ) );
                  if (false !== $del){
                    wp_send_json_success( array( "msg" => sprintf(__("All data regarding Contact form %s (ID %s) were Successfully Deleted.",$this->td), get_the_title($id), $id ) ) );
                  }else{
                    wp_send_json_error( array( "msg" => sprintf(__("Error Deleting Contact form %s (ID %s) data from database.",$this->td), get_the_title($id), $id ) ) );
                  }

                }

                if (isset($_POST["wparam"]) && "clear_db" == trim($_POST["wparam"])) {

                  global $wpdb;
                  $del = $wpdb->query("TRUNCATE TABLE `$this->db_table`");
                  if (false !== $del){
                    wp_send_json_success( array( "msg" => sprintf(__("Database Successfully Cleared.",$this->td),$id ) ) );
                  }else{
                    wp_send_json_error( array( "msg" => sprintf(__("Error Clearing Database.",$this->td),$id ) ) );
                  }

                }

                die();
            }
        }
        /**
         * Add Admin Menu
         *
         * @method admin_menu
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function admin_menu()
        {
            add_menu_page(
                $this->title_w,
                $this->title_s,
                "manage_options",
                $this->db_slug,
                array($this,'db_container'),
                "{$this->assets_url}images/peprodev.svg",
                81
            );
            $menu_title = __("Setting", $this->td);
            add_submenu_page($this->db_slug,$this->title_w, $menu_title, "manage_options", "{$this->db_slug}-setting", array($this,'help_container'));

        }
        /**
         * setting page html callback data
         *
         * @method help_container
         * @param string $hook
         * @return string callback html
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function help_container($hook)
        {
            ob_start();
            $this->update_footer_info();
            $input_number = ' dir="ltr" lang="en-US" min="0" step="1" ';
            $input_english = ' dir="ltr" lang="en-US" ';
            $input_required = ' required ';
            wp_enqueue_style("jQconfirm");
            wp_enqueue_script("jQconfirm");
            wp_enqueue_style("fontawesome","https://use.fontawesome.com/releases/v5.13.1/css/all.css", array(), '5.13.1', 'all');
            wp_enqueue_style("{$this->db_slug}", "{$this->assets_url}css/backend.css");
            wp_enqueue_script("{$this->db_slug}", "{$this->assets_url}js/backend.js", array('jquery'), null, true);
            wp_localize_script("{$this->db_slug}", "_i18n", $this->localize_script());

            is_rtl() AND wp_add_inline_style("{$this->db_slug}", ".form-table th {}#wpfooter, #wpbody-content *:not(.dashicons ), #wpbody-content input:not([dir=ltr]), #wpbody-content textarea:not([dir=ltr]), h1.had, .caqpde>b.fa{ font-family: bodyfont, roboto, Tahoma; }");
            echo "<h1 class='had'>".$this->title_w."</h1><div class=\"wrap\">";
            echo '<form method="post" action="options.php">';
            settings_fields("{$this->db_slug}_general");
            if (isset($_REQUEST["settings-updated"]) && $_REQUEST["settings-updated"] == "true") { echo '<div id="message" class="updated notice is-dismissible"><p>' . _x("Settings saved successfully.", "setting-general", $this->td) . "</p></div>"; }
            echo '<br><table class="form-table"><tbody>';


            $this->print_setting_input("{$this->db_slug}-user_secret_key", _x("User Security Key","setting-general", $this->td), $input_required, "text");
            $this->print_setting_input("{$this->db_slug}-user_api_key", _x("User API Key","setting-general", $this->td), $input_required, "text");
            $this->print_setting_input("{$this->db_slug}-line_number", _x("Line Number","setting-general", $this->td), $input_required, "text");
            $this->print_setting_select("{$this->db_slug}-clearunistall", _x("Clear Configurations on Unistall","setting-general", $this->td),array("yes" =>_x("Yes","settings-general",$this->td), "no" => _x("No","settings-general",$this->td)));
            $this->print_setting_select("{$this->db_slug}-cleardbunistall", _x("Clear Database Data on Unistall","setting-general", $this->td),array("yes" =>_x("Yes","settings-general",$this->td), "no" => _x("No","settings-general",$this->td)));


            echo '</tbody></table><div class="submtCC">';
            submit_button(__("Save setting", $this->td), "primary submt", "submit", false);
            echo "<a class='button button-primary submt' id='emptyDbNow' href='#'>"._x("Empty Database", "setting-general", $this->td)."</a>";
            echo "</form></div></div>";
            $tcona = ob_get_contents();
            ob_end_clean();
            print $tcona;
        }
        /**
         * localize js script
         *
         * @method localize_script
         * @return array i18n array
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function localize_script()
        {
          return array(
            "td"                  => "cf7sms_{$this->td}",
            "ajax"                => admin_url("admin-ajax.php"),
            "home"                => home_url(),
            "nonce"               => wp_create_nonce($this->td),
            "title"               => _x("Select image file", "wc-setting-js", $this->td),
            "btntext"             => _x("Use this image", "wc-setting-js", $this->td),
            "clear"               => _x("Clear", "wc-setting-js", $this->td),
            "currentlogo"         => _x("Current preview", "wc-setting-js", $this->td),
            "selectbtn"           => _x("Select image", "wc-setting-js", $this->td),
            "tr_submit"           => _x("Submit","js-string",$this->td),
            "tr_today"            => _x("Today","js-string",$this->td),
            "errorTxt"            => _x("Error", "wc-setting-js", $this->td),
            "cancelTtl"           => _x("Canceled", "wc-setting-js", $this->td),
            "confirmTxt"          => _x("Confirm", "wc-setting-js", $this->td),
            "successTtl"          => _x("Success", "wc-setting-js", $this->td),
            "submitTxt"           => _x("Submit", "wc-setting-js", $this->td),
            "okTxt"               => _x("Okay", "wc-setting-js", $this->td),
            "txtYes"              => _x("Yes", "wc-setting-js", $this->td),
            "txtNop"              => _x("No", "wc-setting-js", $this->td),
            "cancelbTn"           => _x("Cancel", "wc-setting-js", $this->td),
            "sendTxt"             => _x("Send to all", "wc-setting-js", $this->td),
            "closeTxt"            => _x("Close", "wc-setting-js", $this->td),
            "deleteConfirmTitle"  => _x("Delete Submition", "wc-setting-js", $this->td),
            "deleteConfirmation"  => _x("Are you sure you want to delete submition ID %s ? This cannot be undone.", "wc-setting-js", $this->td),
            "clearDBConfirmation" => _x("Are you sure you want to clear all data from database? This cannot be undone.", "wc-setting-js", $this->td),
            "clearDBConfirmatio2" => _x("Are you sure you want to clear all Current Contact form data from database? This cannot be undone.", "wc-setting-js", $this->td),
            "clearDBConfTitle"    => _x("Clear Database", "wc-setting-js", $this->td),

            "str1"    => sprintf(_x("Contact Form 7 Database Exported via %s", "wc-setting-js", $this->td),"$this->title_w"),
            "str2"    => sprintf(_x("CF7 Database Export", "wc-setting-js", $this->td),$this->title_w),
            "str3"    => sprintf(_x("Exported at %s @ %s", "wc-setting-js", $this->td), date_i18n( get_option('date_format'),current_time( "timestamp")), date_i18n( get_option('time_format'),current_time( "timestamp")),),
            "str4"    => "Pepro-CF7Notifier-". date_i18n("YmdHis",current_time( "timestamp")),
            "str5"    => sprintf(_x("Exported via %s — Export Date: %s @ %s — Developed by Pepro Dev Team ( https://pepro.dev/ )", "wc-setting-js", $this->td),$this->title_w,date_i18n( get_option('date_format'),current_time( "timestamp")), date_i18n( get_option('time_format'),current_time( "timestamp")),),
            "str6"    => "Pepro CF7 Notifier",

            "tbl1"    => _x("No data available in table", "data-table", $this->td),
            "tbl2"    => _x("Showing _START_ to _END_ of _TOTAL_ entries", "data-table", $this->td),
            "tbl3"    => _x("Showing 0 to 0 of 0 entries", "data-table", $this->td),
            "tbl4"    => _x("(filtered from _MAX_ total entries)", "data-table", $this->td),
            "tbl5"    => _x("Show _MENU_ entries", "data-table", $this->td),
            "tbl6"    => _x("Loading...", "data-table", $this->td),
            "tbl7"    => _x("Processing...", "data-table", $this->td),
            "tbl8"    => _x("Search:", "data-table", $this->td),
            "tbl9"    => _x("No matching records found", "data-table", $this->td),
            "tbl10"    => _x("First", "data-table", $this->td),
            "tbl11"    => _x("Last", "data-table", $this->td),
            "tbl12"    => _x("Next", "data-table", $this->td),
            "tbl13"    => _x("Previous", "data-table", $this->td),
            "tbl14"    => _x(": activate to sort column ascending", "data-table", $this->td),
            "tbl15"    => _x(": activate to sort column descending", "data-table", $this->td),
            "tbl16"    => _x("Copy to clipboard", "data-table", $this->td),
            "tbl17"    => _x("Print", "data-table", $this->td),
            "tbl18"    => _x("Export CSV", "data-table", $this->td),
            "tbl19"    => _x("Export Excel", "data-table", $this->td),
            "tbl20"    => _x("Export PDF", "data-table", $this->td),

          );
        }
        /**
         * callback html for list of data saved in database
         *
         * @method db_container
         * @return string html data
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function db_container()
        {
            ob_start();

            $s = true;

            for ($i=0; $i < 2; $i++) {
              $s = !$s;
              $this->update_footer_info();
              $now = current_time('timestamp');
              $randomnum = random_int( 15740, 68414866 );
              $form_id = 114;
              $reciever = "9118629342";
              $msgbody = "Lorem $randomnum ipsum dolor $randomnum sit amet, consectetur $randomnum adipisicing $randomnum elit, sed do eiusmod $randomnum tempor";
              $status = "100";
              $extra_info = serialize(array(
                "your-subject" => "Subj.$randomnum",
                "your-name" => "Name.$randomnum",
                "your-email" => "use$randomnum@gmail.com",
                "your-mobile" => "$randomnum/$randomnum/$randomnum",
              ));
              // $this->save_submition($form_id, $reciever, $status, ($s ? $msgbody : "ULTRAFAST"), $extra_info);
            }

            wp_enqueue_style("{$this->db_slug}", "{$this->assets_url}css/backend.css");

            wp_enqueue_style("datatable");
            wp_enqueue_style("SrchHighlt");
            wp_enqueue_style("jQconfirm");
            wp_enqueue_style("fontawesome","https://use.fontawesome.com/releases/v5.13.1/css/all.css", array(), '5.13.1', 'all');

            wp_enqueue_script("jQconfirm");
            wp_enqueue_script("datatable");
            wp_enqueue_script("highlight.js");
            wp_enqueue_script("SrchHighlt");

            /* needs for PDF export function word properly but due to not supporting utf-8 we ignore these*/
            // wp_enqueue_script( "s1", "https://cdn.datatables.net/buttons/1.6.2/js/dataTables.buttons.min.js", array("jquery"), false);
            // wp_enqueue_script( "s1", "https://cdn.datatables.net/buttons/1.6.2/js/buttons.flash.min.js", array("jquery"), false);
            // wp_enqueue_script( "s2", "https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js", array("jquery"), false);
            // wp_enqueue_script( "s3", "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js", array("jquery"), false);
            // wp_enqueue_script( "s4", "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js", array("jquery"), false);
            // wp_enqueue_script( "s5", "https://cdn.datatables.net/buttons/1.6.2/js/buttons.html5.min.js", array("jquery"), false);
            // wp_enqueue_script( "s6", "https://cdn.datatables.net/buttons/1.6.2/js/buttons.print.min.js", array("jquery"), false);

            wp_enqueue_script("{$this->db_slug}", "{$this->assets_url}/js/backend.js", array('jquery'), null, true);
            wp_localize_script("{$this->db_slug}", "_i18n", $this->localize_script());
            $this->print_table_style();

            is_rtl() AND wp_add_inline_style("{$this->db_slug}", ".form-table th {}#wpfooter, #wpbody-content *:not(.dashicons ), #wpbody-content input:not([dir=ltr]), #wpbody-content textarea:not([dir=ltr]), h1.had, .caqpde>b.fa{ font-family: bodyfont, roboto, Tahoma; }");
            global $wpdb;
            $table = $this->db_table;
            $post_per_page = isset($_GET['per_page']) ? abs((int) $_GET['per_page']) : 100;
            $page = isset($_GET['num']) ? abs((int) $_GET['num']) : 1;
            $offset = ( $page * $post_per_page ) - $post_per_page;
            $title = $this->title;
            echo "<h1 class='had'>$title</h1>";

            $total = $wpdb->get_var("SELECT COUNT(1) FROM $table AS combined_table");
            $res_obj = $wpdb->get_results("SELECT * FROM $table ORDER BY `date_created` DESC LIMIT {$offset}, {$post_per_page}");

            $items_per_page_selceter =
              "<select id='itemsperpagedisplay' name='per_page' style='width:auto !important; margin: 0 0 0 .5rem; float: right;' title='" . __("Items per page", $this->td) . "' >
            		<option value='50' " . selected(100, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 50)."</option>
            		<option value='100' " . selected(100, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 100)."</option>
            		<option value='200' " . selected(200, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 200)."</option>
            		<option value='300' " . selected(300, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 300)."</option>
            		<option value='400' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 400)."</option>
            		<option value='500' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 500)."</option>
            		<option value='600' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 600)."</option>
            		<option value='700' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 700)."</option>
            		<option value='800' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 800)."</option>
            		<option value='900' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 900)."</option>
            		<option value='1000' " . selected(1000, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 1000)."</option>
            		<option disabled>-----------------</option>
            		<option value='$total' " . selected($total, $post_per_page, false) . ">".sprintf(_n( "Show your only saved submition", "Show all %s items at once", $total, $this->td ), $total)."</option>
    		      </select>";

            ?>
            <div class="wrap">
              <form action="<?php echo admin_url("admin.php?page=cf7sms");?>" id='mainform' >
                    <input type="hidden" name="page" value="cf7sms" />
                    <input type="hidden" name="num" value="<?=$page;?>" />
                    <?php

                    // form_id
                    // reciever
                    // status
                    // msgbody
                    // extra_info

                    if (!empty($wpdb->num_rows)) {
                      $header = array(
                        "_sharp_id"     =>  __('ID',$this->td)           ,
                        "date_created"  =>  __('Date Created',$this->td) ,
                        "_cf7_formid"   =>  __('Contact Form',$this->td) ,
                        "your-subject"  =>  __('Subject',$this->td)      ,
                        "email"         =>  __('From',$this->td)         ,
                        "reciever"      =>  __('Reciever',$this->td)     ,
                        "status"        =>  __('Status',$this->td)       ,
                        "msgbody"       =>  __('Message Body',$this->td) ,
                        "utltrafast"    =>  __('Ultra-fast Send',$this->td)   ,
                        "foradmin"      =>  __('Sent to Admin/Submitter',$this->td)   ,
                      );
                      // foreach ( $res_obj as $obj ){
                      //   $data_array = unserialize($obj->extra_info);
                      //   unset($data_array["your-subject"]);
                      //   unset($data_array["your-name"]);
                      //   unset($data_array["your-email"]);
                      //   foreach ( $data_array as $key => $value) {
                      //     // $header[$key] = $key;
                      //   }
                      // }
                      $header["action"] = __('Action',$this->td);
                      $header = array_unique($header);
                      echo "
                            <p><b>". sprintf(_n( "Your very first saved submition is showing below", "%s Saved SMS Log found", $total, $this->td ), $total) . "</b>   {$items_per_page_selceter}</p>
                          			<table border=\"1\" id=\"exported_data\" class=\"exported_data\">
                          			   <thead>
                              			     <tr>";
                                         foreach ($header as $key => $value) {
                                           $extraClass = "";
                                           if (in_array($key, apply_filters( "pepro_cf7sms_hide_col_from_export", array("action","_sharp_id")))){
                                             $extraClass = "noExport";
                                           }
                                           echo "<th class='th-{$key} $extraClass'>{$value}</th>";
                                         }
                    			               echo "
                                         </tr>
                        			     </thead>
                  			           <tbody>";
                                      foreach ( $res_obj as $obj ){
                                        $data_array = unserialize($obj->extra_info);
                                        echo "<tr class=\"item_{$obj->id} status_$obj->status ".("ULTRAFAST" == $obj->msgbody ? "ULTRAFAST" : "NORMAL")." \">";
                                          foreach ($header as $key => $value) {
                                              switch ($key) {
                                                case '_sharp_id':
                                                  $val = $obj->id;
                                                  break;
                                                case 'reciever':
                                                  $val = $obj->reciever;
                                                  break;
                                                case 'status':
                                                  $val = $this->read_status($obj->status);
                                                  break;
                                                case 'foradmin':
                                                  $val = ("YES" == $data_array['_sentforadmin_or_user'])? __("Administrators",$this->td) : __("Submitter",$this->td);
                                                  break;
                                                case 'msgbody':
                                                  $val = $obj->msgbody;
                                                  break;
                                                case 'utltrafast':
                                                  $val = ("ULTRAFAST" == $obj->msgbody ? __("YES",$this->td): __("NO",$this->td)) ;
                                                  break;
                                                case '_cf7_formid':
                                                  $val = "<a target='_blank' href='".admin_url("admin.php?page=wpcf7&post={$obj->form_id}&action=edit")."'>".get_the_title($obj->form_id)." ".sprintf(_x("(ID #%s)","cf7-name-suffix",$this->td),$obj->form_id)."</a>";
                                                  break;
                                                case 'date_created':
                                                  $val = "<p>". date_i18n( get_option('date_format'), $obj->date_created ) . "</p><p>" . date_i18n( get_option('time_format'), $obj->date_created )."</p>";
                                                  break;
                                                case 'email':
                                                  $name = (isset($data_array['your-name'])?$data_array['your-name']:"");
                                                  $email = (isset($data_array['your-email'])?"&lt;{$data_array['your-email']}&gt;":"");
                                                  $val = "{$name} $email";
                                                  break;
                                                case 'action':
                                                  $val = "<a href='javascript:;' title='".esc_attr__("Delete this specific submition", $this->td)."' class=\"button delete_item\" data-lid='{$obj->id}' ><span class='dashicons dashicons-trash'></span></a>
                                                  <span class='spinner loading_{$obj->id}'></span>";
                                                  break;

                                                default:
                                                  $val = nl2br(esc_html($data_array[$key]));
                                                  break;
                                              }
                                          echo "<td class='item_{$key} itd_{$obj->id}'>{$val}</th>";
                                        }
                                        echo "</tr>";
                                      }
                                  echo "</tbody>";
                                echo "</table>";
                                echo '<div class="pagination" style="margin-top: 1.5rem;display: block;">';
                                  echo paginate_links(
                                      array(
                                      'base' => add_query_arg('num', '%#%'),
                                      'format' => '',
                                      'show_all' => false,
                                      'mid_size' => 2,
                                      'end_size' => 2,
                                      'prev_text' => '<span class="button button-primary">' . __('< Previous',$this->td) . "</span>",
                                      'next_text' => '<span class="button button-primary">' . __('Next >',$this->td) . "</span>",
                                      'total' => ceil($total / $post_per_page),
                                      'current' => $page,
                                      'before_page_number' => '<span class="button">',
                                      'after_page_number' => "</span>",
                                      'type' => 'list'
                                      )
                                  );
                                echo "</div>";
                    }
                    else{
                      echo "<h1 align='center' style='font-weight: bold;'>" . __("Error Reading Database!", $this->td) . "</h1>";
                      echo "<h2 align='center'>" . __("It seems there's nothing to show.", $this->td) . "</h2>";
                    }
                  ?>
              </form>
            </div>
            <?php
            $tcona = ob_get_contents();
            ob_end_clean();
            print $tcona;
        }
        /**
         * wp admin init hook
         *
         * @method admin_init
         * @param string $hook
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function admin_init($hook)
        {
            $cf7Notifier_class_options = $this->get_setting_options();
            foreach ($cf7Notifier_class_options as $sections) {
                foreach ($sections["data"] as $id=>$def) {
                    add_option($id, $def);
                    register_setting($sections["name"], $id);
                }
            }
        }
        /**
         * wp admin enqueue scripts hook
         *
         * @method admin_enqueue_scripts
         * @param string $hook
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function admin_enqueue_scripts($hook)
        {

            wp_enqueue_style("{$this->db_slug}-backend-all", "{$this->assets_url}css/backend-all.css", array(), '1.0', 'all');

            wp_register_style("select2",       "{$this->assets_url}css/select2.min.css", false, "4.1.0", "all");
            wp_register_script("select2",      "{$this->assets_url}js/select2.min.js", array( "jquery" ), "4.1.0", true);

            wp_register_style("jQconfirm",     "{$this->assets_url}css/jquery-confirm.css", false, "4.1.0", "all");
            wp_register_script("jQconfirm",    "{$this->assets_url}js/jquery-confirm.js", array( "jquery" ), "4.1.0", true);

            wp_register_style("datatable",     "{$this->assets_url}css/jquery.dataTables.min.css", false, "1.10.21", "all");
            wp_register_script("datatable",    "{$this->assets_url}js/jquery.dataTables.min.js", array( "jquery" ), "1.10.21", true);

            wp_register_style("SrchHighlt",    "{$this->assets_url}css/dataTables.searchHighlight.css", false, "1.0.1", "all");
            wp_register_script("SrchHighlt",   "{$this->assets_url}js/dataTables.searchHighlight.min.js", array( "jquery" ), "1.0.1", true);
            wp_register_script("highlight.js", "{$this->assets_url}js/highlight.js", array( "jquery" ), "3.0.0", true);

        }
        /**
         * save/insert/record data into database
         *
         * @method save_submition
         * @param int $form_id
         * @param string $reciever
         * @param string $status
         * @param string $msgbody
         * @param string $extra_info
         * @return boolean success of failed
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function save_submition($form_id, $reciever, $status, $msgbody, $extra_info)
        {
            global $wpdb;
            $wpdbinsert = $wpdb->insert(
                $this->db_table,
                array(
                  'form_id'     =>  $form_id,
                  'reciever'    =>  $reciever,
                  'status'      =>  $status,
                  'msgbody'     =>  $msgbody,
                  'extra_info'  =>  $extra_info,
                ),
                array(
                  '%d',
                  '%s',
                  '%s',
                  '%s',
                  '%s'
                )
            );
            return $wpdbinsert;
        }
        /**
         * Print Setting Input
         *
         * @method print_setting_input
         * @param string $SLUG
         * @param string $CAPTION
         * @param string $extraHtml
         * @param string $type
         * @param string $extraClass
         * @return string html element
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function print_setting_input($SLUG="", $CAPTION="", $extraHtml="", $type="text",$extraClass="")
        {
            $ON = sprintf(_x("Enter %s", "setting-page", $this->td), $CAPTION);
            echo "<tr>
    			<th scope='row'>
    				<label for='$SLUG'>$CAPTION</label>
    			</th>
    			<td><input name='$SLUG' $extraHtml type='$type' id='$SLUG' placeholder='$CAPTION' title='$ON' value='" . $this->read_opt($SLUG) . "' class='regular-text $extraClass' /></td>
    		</tr>";}
        /**
         * Print Setting Select
         *
         * @method print_setting_select
         * @param string $SLUG
         * @param string $CAPTION
         * @param array $dataArray
         * @return string html element
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function print_setting_select($SLUG, $CAPTION, $dataArray=array())
        {
            $ON = sprintf(_x("Choose %s", "setting-page", $this->td), $CAPTION);
            $OPTS = "";
            foreach ($dataArray as $key => $value) {
                if ($key == "EMPTY") {
                    $key = "";
                }
                $OPTS .= "<option value='$key' ". selected($this->read_opt($SLUG), $key, false) .">$value</option>";
            }
            echo "<tr>
      			<th scope='row'>
      				<label for='$SLUG'>$CAPTION</label>
      			</th>
      			<td><select name='$SLUG' id='$SLUG' title='$ON' class='regular-text'>
            ".$OPTS."
            </select>
            </td>
      		</tr>";
        }
        /**
         * Print Setting Editor
         *
         * @method print_setting_editor
         * @param string $SLUG
         * @param string $CAPTION
         * @param string $re info tips
         * @return string html element
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function print_setting_editor($SLUG, $CAPTION, $re="")
        {
            echo "<tr><th><label for='$SLUG'>$CAPTION</label></th><td>";
            wp_editor(
                $this->read_opt($SLUG, ''), strtolower(str_replace(array('-', '_', ' ', '*'), '', $SLUG)), array(
                'textarea_name' => $SLUG
                )
            );
            echo "<p class='$SLUG'>$re</p></td></tr>";
        }
        /**
         * callback for add option
         *
         * @method _callback
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function _callback($a)
        {
            return $a;
        }
        /**
         * add css to table
         *
         * @method print_table_style
         * @return string css styles
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        protected function print_table_style()
        {
        	if (is_rtl()) {
        		$fC = ":last-child";
        		$lC = ":first-child";
        		echo "<style>body, p, input:not([dir=ltr]), button, h1, h2, h3, h4, h5, h6, textarea:not([dir=ltr]), p, .ui-widget {direction: rtl !important;}</style>";
        	}
        	else{
        		$fC = ":first-child";
        		$lC = ":last-child";
        		echo "<style>body, p, input:not([dir=rtl]), button, h1, h2, h3, h4, h5, h6, textarea:not([dir=rtl]), p, .ui-widget {direction: ltr !important;}</style>";
        	}
        	echo "<style>
        	.sechme {
        		cursor: alias;
        		-webkit-touch-callout: none;
        		-webkit-user-select: none;
        		-khtml-user-select: none;
        		-moz-user-select: none;
        		-ms-user-select: none;
        		user-select: none;
        	}
        	#exported_data_filter input[type=search] {
        		width: 20rem;
        	}
        	.fixedHeader-floating thead{
        		position: relative;
        		top: 2rem;
        	}
        	table{
        		border: solid #ccc 1px;
        		-moz-border-radius: 6px;
        		-webkit-border-radius: 6px;
        	}
        	table tr:hover {
        		background: #fbf8e9;
        		-o-transition: all 0.1s ease-in-out;
        		-webkit-transition: all 0.1s ease-in-out;
        		-moz-transition: all 0.1s ease-in-out;
        		-ms-transition: all 0.1s ease-in-out;
        		transition: all 0.1s ease-in-out;
        	}
      		.loadingdelete{
      			background: url('data:image/gif;base64,R0lGODlhEAALAPQAAP////8AAP7a2v7Q0P7q6v4GBv8AAP4uLv6Cgv5gYP66uv4iIv5KSv6Kiv5kZP6+vv4mJv4EBP5OTv7m5v7Y2P709P44OP7c3P7y8v62tv6goP7Kyv7u7gAAAAAAAAAAACH+GkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAALAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAEAALAAAFLSAgjmRpnqSgCuLKAq5AEIM4zDVw03ve27ifDgfkEYe04kDIDC5zrtYKRa2WQgAh+QQACwABACwAAAAAEAALAAAFJGBhGAVgnqhpHIeRvsDawqns0qeN5+y967tYLyicBYE7EYkYAgAh+QQACwACACwAAAAAEAALAAAFNiAgjothLOOIJAkiGgxjpGKiKMkbz7SN6zIawJcDwIK9W/HISxGBzdHTuBNOmcJVCyoUlk7CEAAh+QQACwADACwAAAAAEAALAAAFNSAgjqQIRRFUAo3jNGIkSdHqPI8Tz3V55zuaDacDyIQ+YrBH+hWPzJFzOQQaeavWi7oqnVIhACH5BAALAAQALAAAAAAQAAsAAAUyICCOZGme1rJY5kRRk7hI0mJSVUXJtF3iOl7tltsBZsNfUegjAY3I5sgFY55KqdX1GgIAIfkEAAsABQAsAAAAABAACwAABTcgII5kaZ4kcV2EqLJipmnZhWGXaOOitm2aXQ4g7P2Ct2ER4AMul00kj5g0Al8tADY2y6C+4FIIACH5BAALAAYALAAAAAAQAAsAAAUvICCOZGme5ERRk6iy7qpyHCVStA3gNa/7txxwlwv2isSacYUc+l4tADQGQ1mvpBAAIfkEAAsABwAsAAAAABAACwAABS8gII5kaZ7kRFGTqLLuqnIcJVK0DeA1r/u3HHCXC/aKxJpxhRz6Xi0ANAZDWa+kEAA7AAAAAAAAAAAAPGJyIC8+CjxiPldhcm5pbmc8L2I+OiAgbXlzcWxfcXVlcnkoKSBbPGEgaHJlZj0nZnVuY3Rpb24ubXlzcWwtcXVlcnknPmZ1bmN0aW9uLm15c3FsLXF1ZXJ5PC9hPl06IENhbid0IGNvbm5lY3QgdG8gbG9jYWwgTXlTUUwgc2VydmVyIHRocm91Z2ggc29ja2V0ICcvdmFyL3J1bi9teXNxbGQvbXlzcWxkLnNvY2snICgyKSBpbiA8Yj4vaG9tZS9hamF4bG9hZC93d3cvbGlicmFpcmllcy9jbGFzcy5teXNxbC5waHA8L2I+IG9uIGxpbmUgPGI+Njg8L2I+PGJyIC8+CjxiciAvPgo8Yj5XYXJuaW5nPC9iPjogIG15c3FsX3F1ZXJ5KCkgWzxhIGhyZWY9J2Z1bmN0aW9uLm15c3FsLXF1ZXJ5Jz5mdW5jdGlvbi5teXNxbC1xdWVyeTwvYT5dOiBBIGxpbmsgdG8gdGhlIHNlcnZlciBjb3VsZCBub3QgYmUgZXN0YWJsaXNoZWQgaW4gPGI+L2hvbWUvYWpheGxvYWQvd3d3L2xpYnJhaXJpZXMvY2xhc3MubXlzcWwucGhwPC9iPiBvbiBsaW5lIDxiPjY4PC9iPjxiciAvPgo8YnIgLz4KPGI+V2FybmluZzwvYj46ICBteXNxbF9xdWVyeSgpIFs8YSBocmVmPSdmdW5jdGlvbi5teXNxbC1xdWVyeSc+ZnVuY3Rpb24ubXlzcWwtcXVlcnk8L2E+XTogQ2FuJ3QgY29ubmVjdCB0byBsb2NhbCBNeVNRTCBzZXJ2ZXIgdGhyb3VnaCBzb2NrZXQgJy92YXIvcnVuL215c3FsZC9teXNxbGQuc29jaycgKDIpIGluIDxiPi9ob21lL2FqYXhsb2FkL3d3dy9saWJyYWlyaWVzL2NsYXNzLm15c3FsLnBocDwvYj4gb24gbGluZSA8Yj42ODwvYj48YnIgLz4KPGJyIC8+CjxiPldhcm5pbmc8L2I+OiAgbXlzcWxfcXVlcnkoKSBbPGEgaHJlZj0nZnVuY3Rpb24ubXlzcWwtcXVlcnknPmZ1bmN0aW9uLm15c3FsLXF1ZXJ5PC9hPl06IEEgbGluayB0byB0aGUgc2VydmVyIGNvdWxkIG5vdCBiZSBlc3RhYmxpc2hlZCBpbiA8Yj4vaG9tZS9hamF4bG9hZC93d3cvbGlicmFpcmllcy9jbGFzcy5teXNxbC5waHA8L2I+IG9uIGxpbmUgPGI+Njg8L2I+PGJyIC8+CjxiciAvPgo8Yj5XYXJuaW5nPC9iPjogIG15c3FsX3F1ZXJ5KCkgWzxhIGhyZWY9J2Z1bmN0aW9uLm15c3FsLXF1ZXJ5Jz5mdW5jdGlvbi5teXNxbC1xdWVyeTwvYT5dOiBDYW4ndCBjb25uZWN0IHRvIGxvY2FsIE15U1FMIHNlcnZlciB0aHJvdWdoIHNvY2tldCAnL3Zhci9ydW4vbXlzcWxkL215c3FsZC5zb2NrJyAoMikgaW4gPGI+L2hvbWUvYWpheGxvYWQvd3d3L2xpYnJhaXJpZXMvY2xhc3MubXlzcWwucGhwPC9iPiBvbiBsaW5lIDxiPjY4PC9iPjxiciAvPgo8YnIgLz4KPGI+V2FybmluZzwvYj46ICBteXNxbF9xdWVyeSgpIFs8YSBocmVmPSdmdW5jdGlvbi5teXNxbC1xdWVyeSc+ZnVuY3Rpb24ubXlzcWwtcXVlcnk8L2E+XTogQSBsaW5rIHRvIHRoZSBzZXJ2ZXIgY291bGQgbm90IGJlIGVzdGFibGlzaGVkIGluIDxiPi9ob21lL2FqYXhsb2FkL3d3dy9saWJyYWlyaWVzL2NsYXNzLm15c3FsLnBocDwvYj4gb24gbGluZSA8Yj42ODwvYj48YnIgLz4K') no-repeat 50% 50%;
      			display: none;
      			width: 100%;
      			height: 35px;
      		}
      		table td, table th {
      			border-left: 1px solid #ccc;
      			border-top: 1px solid #ccc;
      			padding: 10px;
      			text-align: left;
      		}
      		table th {
      			background-color: #dce9f9;
      			background-image: -webkit-gradient(linear, left top, left bottom, from(#ebf3fc), to(#dce9f9));
      			background-image: -webkit-linear-gradient(top, #ebf3fc, #dce9f9);
      			background-image:    -moz-linear-gradient(top, #ebf3fc, #dce9f9);
      			background-image:     -ms-linear-gradient(top, #ebf3fc, #dce9f9);
      			background-image:      -o-linear-gradient(top, #ebf3fc, #dce9f9);
      			background-image:         linear-gradient(top, #ebf3fc, #dce9f9);
      			border-top: none;
      			text-shadow: 0 1px 0 rgba(255,255,255,.5);
      		}
      		table th, table td {
      			text-align: center;
      		}
      		.pagination>ul{
      			margin: 0 !important;
      			padding: 0 !important;
      			cursor: default;
      		}
      		.pagination>ul>li{
      			display: inline-block;
      			padding: 4px;
      			margin: 0 !important;
      		}
      		.pagination {
      			text-align: center;
      			display: inline;
      		}
      		.pagination > ul > li > a.page-numbers,
          .pagination > ul > li > span.current{
            display: block;
            height: auto;
      		}
      		.pagination > ul > li > span.current *{
            color: gray;
            border-color: gray;
            pointer-events: none;
      		}
      		p[dir=ltr]{
      			font-family: roboto ,Arial !important;
      			dir: ltr !important;
      			text-align:left !important;
      			padding : 12px;
      		}
        		</style>";
        }
        /**
         * convert sms send status to human-readable text
         *
         * @method read_status
         * @param string $st status
         * @return string transalted human-readable status
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        protected function read_status($status)
        {
          switch ($status) {
            case '100':
              return __("SMS Submission completed successfully",$this->td);
              break;
            case '400':
              return __("SMS Submission Failed",$this->td);
              break;
            default:
              return $st;
              break;
          }
        }
        /**
         * Send Normal SMS
         *
         * @method send_normal_sms
         * @param int $MobileNumbers
         * @param string $Messages
         * @version 1.0.0
         * @since 1.0.0
         * @return boolean status
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function send_normal_sms($MobileNumbers,$Messages)
        {
          try {
            date_default_timezone_set("Asia/Tehran");
            @$SendDateTime = date("Y-m-d")."T".date("H:i:s");
            $SendMessage = $this->sendMessage($MobileNumbers, $Messages, $SendDateTime);
            return $SendMessage;
          } catch (Exeption $e) {
            return 'Error SendMessage : '.$e->getMessage();
          }
          return false;
        }
        /**
         * Send Ultrafast SMS
         *
         * @method send_ultrafast_sms
         * @param array $data
         * @version 1.0.0
         * @since 1.0.0
         * @return boolean status
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function send_ultrafast_sms($data)
        {
          try {
              date_default_timezone_set("Asia/Tehran");
              $UltraFastSend = $this->ultraFastSend($data);
              return $UltraFastSend;
          } catch (Exeption $e) {
              return 'Error UltraFastSend: '.$e->getMessage();
          }
          return false;
        }
        /**
         *
         * Gets API Ultra Fast Send Url.
         *
         *
         * @return string Indicates the Url
         */
        protected function getAPIUltraFastSendUrl()
        {
            return "api/UltraFastSend";
        }
        /**
         *
         * Gets Api Token Url.
         *
         *
         * @return string Indicates the Url
         */
        protected function getApiTokenUrl()
        {
            return "api/Token";
        }
        /**
         *
         * Ultra Fast Send Message.
         *
         *
         * @param  data[] $data array structure of message data
         *
         * @return string Indicates the sent sms result
         */
        public function ultraFastSend($data)
        {
            $token = $this->_getToken($this->APIKey, $this->SecretKey);
            if ($token != false) {
                $postData = $data;

                $url = $this->APIURL.$this->getAPIUltraFastSendUrl();
                $UltraFastSend = $this->_execute($postData, $url, $token);

                $object = json_decode($UltraFastSend);

                $result = false;
                if (is_object($object)) {
                    $result = $object->Message;
                } else {
                    $result = false;
                }
            } else {
                $result = false;
            }
            return $result;
        }
        /**
         *
         * Gets token key for all web service requests.
         *
         *
         * @return string Indicates the token key
         */
        private function _getToken()
        {
            $postData = array(
            'UserApiKey' => $this->APIKey,
            'SecretKey' => $this->SecretKey,
            'System' => 'php_rest_v_2_0'
            );
            $postString = json_encode($postData);

            $ch = curl_init($this->APIURL.$this->getApiTokenUrl());
            curl_setopt(
                $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json'
                )
            );
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);

            $result = curl_exec($ch);
            curl_close($ch);

            $response = json_decode($result);

            $resp = false;
            $IsSuccessful = '';

            $TokenKey = '';

            if (is_object($response)) {
                $IsSuccessful = $response->IsSuccessful;
                if ($IsSuccessful == true) {

                    $TokenKey = $response->TokenKey;
                    $resp = $TokenKey;
                } else {
                    $resp = false;
                }
            }
            return $resp;
        }
        /**
         *
         * Executes the main method.
         *
         *
         * @param  postData[] $postData array of json data
         * @param  string     $url      url
         * @param  string     $token    token string
         *
         * @return string Indicates the curl execute result
         */
        private function _execute($postData, $url, $token)
        {
            $postString = json_encode($postData);

            $ch = curl_init($url);
            curl_setopt(
                $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'x-sms-ir-secure-token: '.$token
                )
            );
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);

            $result = curl_exec($ch);
            curl_close($ch);

            return $result;
        }
        /**
         * Gets API Message Send Url.
         *
         * @return string Indicates the Url
         */
        protected function getAPIMessageSendUrl()
        {
            return "api/MessageSend";
        }
        /**
         * Send sms.
         *
         * @param MobileNumbers[] $MobileNumbers array structure of mobile numbers
         * @param Messages[]      $Messages      array structure of messages
         * @param string          $SendDateTime  Send Date Time
         *
         * @return string Indicates the sent sms result
         */
        public function sendMessage($MobileNumbers, $Messages, $SendDateTime = '')
        {
            $token = $this->_getToken($this->APIKey, $this->SecretKey);

            if ($token != false) {
                $postData = array(
                'Messages' => $Messages,
                'MobileNumbers' => $MobileNumbers,
                'LineNumber' => $this->LineNumber,
                'SendDateTime' => $SendDateTime,
                'CanContinueInCaseOfError' => 'false'
                );

                $url = $this->APIURL.$this->getAPIMessageSendUrl();
                $SendMessage = $this->_execute($postData, $url, $token);
                $object = json_decode($SendMessage);

                $result = false;
                if (is_object($object)) {
                    $result = $object->Message;
                } else {
                    $result = false;
                }
            } else {
                $result = false;
            }
            return $result;
        }
        /* common functions */
        public function read_opt($mc, $def="")
        {
            return get_option($mc) <> "" ? get_option($mc) : $def;
        }
        public function plugins_row_links($links)
        {
            foreach ($this->get_manage_links() as $title => $href) {
                array_unshift($links, "<a href='$href' target='_self'>$title</a>");
            }
            $a = new SimpleXMLElement($links["deactivate"]);
            $this->deactivateURI = "<a href='".$a['href']."'>".$this->deactivateICON.$a[0]."</a>";
            unset($links["deactivate"]);
            return $links;
        }
        public function plugin_row_meta($links, $file)
        {
            if ($this->plugin_basename === $file) {
                // unset($links[1]);
                unset($links[2]);
                $icon_attr = array(
                  'style' => array(
                  'font-size: larger;',
                  'line-height: 1rem;',
                  'display: inline;',
                  'vertical-align: text-top;',
                  ),
                );
                foreach ($this->get_meta_links() as $id => $link) {
                    $title = (!empty($link['icon'])) ? self::do_icon($link['icon'], $icon_attr) . ' ' . esc_html($link['title']) : esc_html($link['title']);
                    $links[ $id ] = '<a href="' . esc_url($link['url']) . '" title="'.esc_attr($link['description']).'" target="'.(empty($link['target'])?"_blank":$link['target']).'">' . $title . '</a>';
                }
                $links[0] = $this->versionICON . $links[0];
                $links[1] = $this->authorICON . $links[1];
                $links["deactivate"] = $this->deactivateURI;
            }
            return $links;
        }
        public static function do_icon($icon, $attr = array(), $content = '')
        {
            $class = '';
            if (false === strpos($icon, '/') && 0 !== strpos($icon, 'data:') && 0 !== strpos($icon, 'http')) {
                // It's an icon class.
                $class .= ' dashicons ' . $icon;
            } else {
                // It's a Base64 encoded string or file URL.
                $class .= ' vaa-icon-image';
                $attr   = self::merge_attr(
                    $attr, array(
                    'style' => array( 'background-image: url("' . $icon . '") !important' ),
                    )
                );
            }

            if (! empty($attr['class'])) {
                $class .= ' ' . (string) $attr['class'];
            }
            $attr['class']       = $class;
            $attr['aria-hidden'] = 'true';

            $attr = self::parse_to_html_attr($attr);
            return '<span ' . $attr . '>' . $content . '</span>';
        }
        public static function parse_to_html_attr($array)
        {
            $str = '';
            if (is_array($array) && ! empty($array)) {
                foreach ($array as $attr => $value) {
                    if (is_array($value)) {
                        $value = implode(' ', $value);
                    }
                    $array[ $attr ] = esc_attr($attr) . '="' . esc_attr($value) . '"';
                }
                $str = implode(' ', $array);
            }
            return $str;
        }
    }
    /**
     * load plugin and load textdomain then set a global varibale to access plugin class!
     *
     * @version 1.0.0
     * @since   1.0.0
     * @license https://pepro.dev/license Pepro.dev License
     */
    add_action(
        "plugins_loaded", function () {
            global $cf7Notifier;
            load_plugin_textdomain("cf7sms", false, dirname(plugin_basename(__FILE__))."/languages/");
            $cf7Notifier = new cf7Notifier;
            register_activation_hook(__FILE__, array("cf7Notifier", "activation_hook"));
            register_deactivation_hook(__FILE__, array("cf7Notifier", "deactivation_hook"));
            register_uninstall_hook(__FILE__, array("cf7Notifier", "uninstall_hook"));
        }
    );
}
/*################################################################################
END OF PLUGIN || Programming is art // Artist : Amirhosseinhpv [https://hpv.im/]
// */
