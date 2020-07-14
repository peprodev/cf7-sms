 (function($) {
   $(document).ready(function() {

     $.each($(".cf7smscheckbox"),function(e) {
       let me = $(this);
       if (me.prop("checked")){
         me.parent().parent().find("p.extra").show();
       }else{
         me.parent().parent().find("p.extra").hide();
       }
     });

     if ($("#pepro_cf7sms_active_sms_admin_fast").prop("checked")){
       $("#pepro_cf7sms_admin_sms_text").addClass("disabled");
       $("#pepro_cf7sms_active_sms_admin_fast_id").removeClass("disabled");
       $("#pepro_cf7sms_active_sms_admin_fast_ids").removeClass("disabled");
     }else{
       $("#pepro_cf7sms_admin_sms_text").removeClass("disabled");
       $("#pepro_cf7sms_active_sms_admin_fast_id").addClass("disabled");
       $("#pepro_cf7sms_active_sms_admin_fast_ids").addClass("disabled");
     }

     if ($("#pepro_cf7sms_active_sms_user_fast").prop("checked")){
       $("#pepro_cf7sms_user_sms_text").addClass("disabled");
       $("#pepro_cf7sms_active_sms_user_fast_id").removeClass("disabled");
       $("#pepro_cf7sms_active_sms_user_fast_ids").removeClass("disabled");
     }else{
       $("#pepro_cf7sms_user_sms_text").removeClass("disabled");
       $("#pepro_cf7sms_active_sms_user_fast_id").addClass("disabled");
       $("#pepro_cf7sms_active_sms_user_fast_ids").addClass("disabled");
     }

     $(document).on("change", "#pepro_cf7sms_active_sms_admin_fast", function(e) {
       e.preventDefault();
       let me = $(this);
       if (me.prop("checked")){
         $("#pepro_cf7sms_active_sms_admin_fast_id").removeClass("disabled");
         $("#pepro_cf7sms_active_sms_admin_fast_ids").removeClass("disabled");
         $("#pepro_cf7sms_admin_sms_text").addClass("disabled");
       }else{
         $("#pepro_cf7sms_active_sms_admin_fast_id").addClass("disabled");
         $("#pepro_cf7sms_active_sms_admin_fast_ids").addClass("disabled");
         $("#pepro_cf7sms_admin_sms_text").removeClass("disabled");
       }
     });

     $(document).on("change", "#pepro_cf7sms_active_sms_user_fast", function(e) {
       e.preventDefault();
       let me = $(this);
       if (me.prop("checked")){
         $("#pepro_cf7sms_user_sms_text").addClass("disabled");
         $("#pepro_cf7sms_active_sms_user_fast_id").removeClass("disabled");
         $("#pepro_cf7sms_active_sms_user_fast_ids").removeClass("disabled");
       }else{
         $("#pepro_cf7sms_user_sms_text").removeClass("disabled");
         $("#pepro_cf7sms_active_sms_user_fast_id").addClass("disabled");
         $("#pepro_cf7sms_active_sms_user_fast_ids").addClass("disabled");
       }
     });

     $(document).on("change", ".cf7smscheckbox", function(e) {
       e.preventDefault();
       let me = $(this);
       if (me.prop("checked")){
         me.parent().parent().find("p.extra").show();
       }else{
         me.parent().parent().find("p.extra").hide();
       }
     });
   });
 })(jQuery);
