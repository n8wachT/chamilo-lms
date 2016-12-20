<?php
/* For licensing terms, see /license.txt */

/**
 * @author Juan Carlos Trabado herodoto@telefonica.net
 * @package chamilo.social
 */

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';

api_block_anonymous_users();

if (api_get_setting('allow_my_files') === 'false') {
    api_not_allowed(true);
}

$htmlHeadXtra[] = '
<script>

function denied_friend (element_input) {
	name_button=$(element_input).attr("id");
	name_div_id="id_"+name_button.substring(13);
	user_id=name_div_id.split("_");
	friend_user_id=user_id[1];
	 $.ajax({
		contentType: "application/x-www-form-urlencoded",
		beforeSend: function(objeto) {
		$("#id_response").html("<img src=\''.Display::return_icon('indicator.gif', '', ICON_SIZE_SMALL, true, true).'\' />"); },
		type: "POST",
		url: "' . api_get_path(WEB_AJAX_PATH) . 'social.ajax.php?a=deny_friend",
		data: "denied_friend_id="+friend_user_id,
		success: function(datos) {
		 $("div#"+name_div_id).hide("slow");
		 $("#id_response").html(datos);
		}
	});
}
function register_friend(element_input) {
    if(confirm("' . get_lang('AddToFriends') . '")) {
    	name_button=$(element_input).attr("id");
    	name_div_id="id_"+name_button.substring(13);
    	user_id=name_div_id.split("_");
    	user_friend_id=user_id[1];
    	 $.ajax({
    		contentType: "application/x-www-form-urlencoded",
    		beforeSend: function(objeto) {
    		$("div#dpending_"+user_friend_id).html("<img src=\''.Display::return_icon('indicator.gif', '', ICON_SIZE_SMALL, true, true).'\' />"); },
    		type: "POST",
    		url: "' . api_get_path(WEB_AJAX_PATH) . 'social.ajax.php?a=add_friend",
    		data: "friend_id="+user_friend_id+"&is_my_friend="+"friend",
    		success: function(datos) {  $("div#"+name_div_id).hide("slow");
    			$("form").submit()
    		}
    	});
    }
}

$(document).on("ready", function () {
    $("#el-finder").elfinder({
        url: "' . api_get_path(WEB_LIBRARY_PATH) . 'elfinder/php/connector.php",
        lang: "' . api_get_language_isocode() . '",
        height: 600,
        resizable: false,
        rememberLastDir: false,
    }).elfinder("instance");
});

</script>';

// Social Menu Block
$social_menu_block = SocialManager::show_social_menu('myfiles');
$actions = null;

if (isset($_GET['cidReq'])) {
    $actions = '<a href="' . api_get_path(
            WEB_CODE_PATH
        ) . 'document/document.php?cidReq=' . Security::remove_XSS(
            $_GET['cidReq']
        ) . '&amp;id_session=' . Security::remove_XSS(
            $_GET['id_session']
        ) . '&amp;gidReq=' . Security::remove_XSS(
            $_GET['gidReq']
        ) . '&amp;id=' . Security::remove_XSS(
            $_GET['parent_id']
        ) . '">' . Display::return_icon(
            'back.png',
            get_lang('BackTo') . ' ' . get_lang('Documents') . ' (' . get_lang(
                'Course'
            ) . ')'
        ) . '</a>';
}

if (api_get_setting('allow_social_tool') == 'true') {
    $_SESSION['this_section'] = SECTION_SOCIAL;
    $interbreadcrumb[] = array(
        'url' => 'profile.php',
        'name' => get_lang('SocialNetwork')
    );
} else {
    $_SESSION['this_section'] = SECTION_COURSES;
    $interbreadcrumb[] = array(
        'url' => api_get_path(WEB_PATH).'user_portal.php',
        'name' => get_lang('MyCourses')
    );
}

$interbreadcrumb[] = array('url' => '#', 'name' => get_lang('MyFiles'));

$tpl = new Template();
SocialManager::setSocialUserBlock($tpl, api_get_user_id(), 'myfiles');
$editor = new \Chamilo\CoreBundle\Component\Editor\Editor();
$editor = $tpl->fetch('default/'.$editor->getEditorStandAloneTemplate());

$tpl->assign('show_media_element', 0);

if (api_get_setting('allow_social_tool') == 'true') {
    $tpl->assign('social_menu_block', $social_menu_block);
    $tpl->assign('social_right_content', $editor);
    $social_layout = $tpl->get_template('social/myfiles.tpl');
    $tpl->display($social_layout);
} else {
    $controller = new IndexManager(get_lang('MyCourses'));
    $tpl->assign('actions', $actions);
    $tpl->assign('content', $editor);
    $tpl->assign('profile_block', $controller->return_profile_block());
    $tpl->assign('user_image_block', $controller->return_user_image_block());
    $tpl->assign('course_block', $controller->return_course_block());
    $tpl->display_two_col_template();
}
