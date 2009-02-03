<?php


require_once("util.php");


$popup_id=0;



function make_popup($title, $label, $content) 
{
    global $popup_id;
    $popup_id++;
    
    return "
    <a href='javascript:popupShow(\"popup_$popup_id\")'>$label</a>
    <div class='anchor'>
    <div class='popup' id='popup_$popup_id'>
    <div class='popup_title'>
    $title
    <a href='javascript:popupHide(\"popup_$popup_id\")'>x</a>
    </div>
    <div class='popup_content'>
$content
    </div>
    </div>
    </div>
";
    
    
}


function sortby($col)
{
    $out = array();
    
    $curr_col = getParam("sort", "approval_time");
    $curr_order = getParam("order", "desc");

    if($col != 'approval_time') {
        $out['sort'] = $col;
    }
    else {
        $out['sort'] = null;
    }
    
    if(($curr_col==$col) && ($curr_order=='desc')) {
        $out['order'] = 'asc';
    }
    else {
        $out['order'] = null;
    }

    return $out;
    
}

function decorator($col) 
{
    $curr_col = getParam("sort", "approval_time");
    $curr_order = getParam("order", "desc");
    
    if ($col != $curr_col) {
        return "";
    }
    
    return $curr_order=='desc'?"&darr;":"&uarr;";
    
}

function showStatus($me)
{
    showWhitelist();
    showUsers();
    showLog();
}

function showWhitelist()
{
    

    $base_url="?";
    
    $url_mac=makeUrl(sortby("mac"));
    $url_approver=makeUrl(sortby("approver"));
    $url_approval_time=makeUrl(sortby("approval_time"));

    $mac_decorator = decorator("mac");
    $approver_decorator = decorator("approver");
    $approval_time_decorator = decorator("approval_time");
    
    $col = getParam("sort", "approval_time");
    $order = getParam("order", "desc");

    echo "
<div class='whitelist_edit'>
<h2>Whitelist</h2>
";

    $macText = htmlEncode(getParam('mac',""));
        
    echo "
<h3>Add whitelist item</h3>

<form method='post' onsubmit='return validateMac(this.elements[\"mac\"].value);'>
<label for='mac'>Mac:</label><input type='text' name='mac' value='$macText' size='15' id='mac'>
<input type='hidden' name='action' value='whitelist_add'>
<button type='submit'>Add</button>
</form>
";


    $msg_count = whitelistGetCount(getParam("filter"));
    
    $pager = makePager("wl_page", $msg_count);
    $wl_page = getParam("wl_page", 1);
    $wl_count = PAGER_PAGES;

    $filterStr = htmlEncode(getParam('filter',''));
    

    echo "
<h3>Current Whitelist</h3>
<form action ='' type='get'>
<label for='whitelist_filter'>Filter:</label> <input name='filter' id='whitelist_filter' value='$filterStr' size='10'>
<button type='submit'>Filter</button>
</form>
$pager
<table id='whitelist_table'>
<tr>
  <th>
    <a href='$url_mac'>MAC$mac_decorator</a>
  </th>
  <th>
    <a href='$url_approver'>Approved by$approver_decorator</a>
  </th>
  <th>
    <a href='$url_approval_time'>Approval date$approval_time_decorator</a>
  </th>
  <th>
  </th>
</tr>

";
    
    foreach(whitelistGet("$col $order", getParam("filter"),($wl_page-1) * $wl_count, $wl_count) as $wl) 
    {
        echo "
<tr>
  <td>
    ".$wl->mac."
  </td>
  <td>
    ".$wl->approver."
  </td>
  <td>
    ".date("Y-m-d H:i", $wl->approval_time)."
  </td>
  <td>
    <a href='?action=whitelist_remove&amp;mac=".htmlEncode($wl->mac)."'>remove</a>
  </td>

</tr>";
        
    }
    
    
    
    echo "
</table>
$pager

</div>

";


}

function showUsers()
{
    
    $nun = htmlEntities(getParam("new_username", ""));
    $nfn = htmlEntities(getParam("new_fullname", ""));
    $np1 = htmlEntities(getParam("new_password", ""));
    $np2 = htmlEntities(getParam("new_password2", ""));

   

    echo "
<div class='user_edit'>
<h2>Users</h2>
";
    echo "

<form method='post' onsubmit='return validateUserForm(this, true);'>
<table>
<tr>
  <td><label for='new_username'>Username:</td></td>
  <td><input type='text' id='new_username' name='new_username' value='$nun'></td>
</tr>
<tr>
  <td><label for='new_fullname'>Full name:</td></td>
  <td><input type='text' id='new_fullname' name='new_fullname' value='$nfn'></td>
</tr>
<tr>
  <td><label for='new_password'>Password:</td></td>
  <td><input type='password' id='new_password' name='new_password' value='$np1'></td>
</tr>
<tr>
  <td><label for='new_password2'>Password (Again):</td></td>
  <td><input type='password' id='new_password2' name='new_password2' value='$np2'></td>
</tr>
</table>
<input type='hidden' name='action' value='user_add'>
<button type='submit'>Add user</button>
</form>
";
    


    echo "<table>

<tr>
  <th>
    Username
  </th>
  <th>
    Fullname
  </th>
  <th>
  </th>
</tr>

";
    
    foreach(userGet() as $u) 
    {
        $popup = make_popup("Edit user «". $u->username."»", "edit", "
<form action='' method='post' onsubmit='return validateUserForm(this, false);'>
<table>
 <tr>
  <td><label for='new_username'>User name:</td></td>
  <td><input type='text' id='new_username' name='new_username' value='".htmlEncode($u->username)."'></td>
 </tr>
 <tr>
  <td><label for='new_fullname'>Full name:</td></td>
  <td><input type='text' id='new_fullname' name='new_fullname' value='".htmlEncode($u->fullname)."'></td>
 </tr>
 <tr>
  <td><label for='new_password'>Password:</td></td>
  <td><input type='password' id='new_password' name='new_password'></td>
 </tr>
 <tr>
  <td><label for='new_password2'>Password (Again):</td></td>
  <td><input type='password' id='new_password2' name='new_password2'></td>
 </tr>
</table>
<input type='hidden' name='action' value='user_edit'>
<input type='hidden' name='id' value='".$u->id."'>
<button type='submit'>Update</button>
</form>
");
        
        $remove = $u->id != $me->id ?"<a href='?action=user_remove&amp;id=".htmlEncode($u->id)."'>remove</a>":"";
        

        echo "
<tr>
  <td>
    ".$u->username."
  </td>
  <td>
    ".$u->fullname."
  </td>
  <td>
    $remove
  </td>
  <td>
    $popup
  </td>
</tr>";
        
    }
    
    
    
    echo "
</table>
</div>
";


}

function showLog()
{
    $msg_count = logGetCount();

    $pager = makePager("log_page", $msg_count);
    $log_page = getParam("log_page", 1);
    $log_count = PAGER_PAGES;
    
    
    echo "
<div class='log_view'>
<h2>Event log</h2>
$pager
<table>
<tr>
  <th>
    Message
  </th>
  <th>
    User
  </th>
  <th>
    Time
  </th>
</tr>

";
    foreach(logGet(($log_page-1) * $log_count, $log_count) as $log) {
        echo "<tr><td>".$log['message']."</td><td>".$log['username']."</td><td>".date("Y-m-d H:i", (int)$log['log_time'])."</td></tr>";
    }
    echo "
</table>
$pager
";
    
    echo "</div>";
    
    
}

function checkLogin() 
{
    
    if (array_key_exists("session", $_REQUEST)) {
        
        $session = $_REQUEST['session'];

        $me = checkSession($session);
        if ($me) {
            return $me;
        }
    }

    $username = "";
    
    if(array_key_exists("username", $_REQUEST)) {
        $username = htmlEncode($_REQUEST['username']);

        if(array_key_exists("password", $_REQUEST)) {
            $username = htmlEncode($_REQUEST['username']);
            $password = htmlEncode($_REQUEST['password']);
            
            $me = createSession($username, $password);
            if ($me) {
                $extra = array();
                foreach(array($_GET, $_POST) as $arr) {
                    foreach($arr as $key => $value) {
                        if( $key != 'username' && $key != 'password') {
                            $extra[]= urlEncode($key)."=".urlEncode($value);
                        }
                    }
                }
                
                redirect("index.php?" . implode("&", $extra));
            }
            else {
                echo "Wrong username or password";
            }
        }
    }
    
    $extra="";
    foreach($_GET as $key => $value) {
        if( $key != 'username' && $key != '$password') {
            $extra .= "<input type='hidden' name='".htmlEncode($key)."' value='".htmlEncode($value)."'>\n";
        }
    }
    
    echo "
<div class='login'>
<h1>Mac police log in</h1>
<p>
Please enter you username and password in order to use Mac police.
<form name=login method=post action='index.php'>
</p>
<table>
<tr>
<td>
<label for='username'>Username:</label>
</td>
<td>
<input id='username' type='text' name='username' value='$username' size='12'>
</td>
</tr>

<tr>
<td>
<label for='password'>Password:</label>
</td>
<td>
<input id='password' type='password' name='password' size='12'>
</td>
</tr>
</table>

$extra
<button type='submit'>Login</button>
</form>
</div>
"; 

    return false;
    
}

function writeBlurb($me) 
{
    $msg = $_REQUEST['message_str'];

    unset($_REQUEST['message_str']);
    unset($_GET['message_str']);

    if ($msg) {
        $msg = "<div class='message_view'>$msg</div>";
    }
    
    
    echo "<div class='blurb'><p>Logged in as " . $me->username . "
</p>
<form type='post' action=''>
<input type='hidden' name='action' value='logout'>
<button type='submit'>Log out</button>
</form>
</div>
<div class='messages'>
$msg
</div>";

}


function main() 
{
    ob_start();
    $action = getParam("action","status");
    
    if ($action == 'whitelist_get') {
        
        foreach (whitelistGet() as $wl) {
            echo $wl->mac . "\n";
            
            
        }
        ob_flush();
        exit(0);
    }

    writeHeader("Mac police - " . $action . " page");

    if($me = checkLogin()) {

        echo "
<h1>Mac-police status page</h1>
";
    
        writeBlurb($me);
                
        switch($action) {
                
        case 'discover':
            discover();
            break;
            
        case 'status':
            showStatus($me);
            break;
            
        case 'logout':
            destroySession($me->session);
            redirect();
            break;

        case 'whitelist_add':
            whitelistAdd($_REQUEST['mac'], $me) && redirect();
            showStatus($me);
            break;
            
        case 'whitelist_remove':
            whitelistRemove($_REQUEST['mac'], $me) && redirect();
            showStatus($me);
            break;
            
        case 'user_add':
            userAdd($_REQUEST['new_username'], $_REQUEST['new_password'], $_REQUEST['new_fullname'], $me) && redirect();
            showStatus($me);
            break;
            
        case 'user_edit':
            userEdit($_REQUEST['id'], $_REQUEST['new_username'], $_REQUEST['new_password'], $_REQUEST['new_fullname'], $me) && redirect();
            showStatus($me);
            break;
            
        case 'user_remove':
            userRemove($_REQUEST['id'], $me);
            redirect();
            break;
            
        default:
            error("Unknown action " . $action);
            break;
        }
    }
    
    writeFooter();
    ob_end_flush();
    
}

main();



?>