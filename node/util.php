<?php

define("DB_VERSION", "1.0");

define("STATUS_UNKNOWN", 0);
define("STATUS_WHITELISTED", 1);

define("DB_FILE", "/tmp/mac_police_db.sqlite");

define("SESSION_TIMEOUT", 3600);
define("PAGER_PAGES", 25);

/**
        A class representing a machine on the local network.
*/
global $whitelist;
global $db;

function htmlEncode($str)
{
    return htmlEntities($str, ENT_QUOTES, "UTF-8");    
}



function nmapExec($mode, $network)
{
    $output = array();
    $status = 0;
    
    $cmd = "/share/MD0_DATA/freecode/nmapwrapper $mode $network 2>&1";
    
    exec($cmd, $output, $status);
    
    if($status) {
        echo "nmap non-zero exit status $status.";
        echo "Error message:<pre>";
        echo implode("\n",$output);        
        echo "</pre>";
        return null;
    }
    
    return $output;
    
}

/**
        Scan the specified network and return all hosts in it. 
        
*/
function nmapGetMac($ip)
{
    $output = nmapExec("-A", escapeshellarg($ip));
    if (!$output) {
        return null;
    }
    
    $re1 = '^Host ([^ ]*) *(\((.*)\))? *appears to be up.$';
    $re2 = '^MAC Address: ([^ ]*) *(\(.*\))?$';
    $host_arr=array();
    $mac_arr=array();
    $res = array();
    
    for($i = 0; $i < count($output)-1; $i++) {
        if (ereg($re1, $output[$i],$host_arr) && ereg($re2, $output[$i+1],$mac_arr)) {
            $ip = $host_arr[3]?$host_arr[3]:$host_arr[1];
            $res[] = new host($ip, $host_arr[1], $mac_arr[1]);
        }
    }
    
    return $res;
  
}


class dbItem
{

    /**
     * Returns an array of all public properties of this object
     * type. By convention, this is exactly the same as the list of
     * fields in the database, and also the same thing as all fields
     * whose name does not begin with an underscore.
     */
    function getPublicProperties() {
        static $cache = null;
        if (is_null( $cache )) {
            $cache = array();
            foreach (get_class_vars( get_class( $this ) ) as $key=>$val) {
                if (substr( $key, 0, 1 ) != '_') {
                    $cache[] = $key;
                }
            }
        }
        return $cache;
    }

    function initFromArray($arr)
    {
        $count = 0;
        
        foreach ($this->getPublicProperties() as $key) {
            if (array_key_exists($key, $arr)) {
                $this->$key = $arr[$key];
                $count ++;
            }
        }
        return $count;
        
        
    }
    

}


class user
        extends dbItem
{
    var $username;
    var $id;
    var $fullname;
    var $session;
    
    function user($data, $session) 
    {
        if ($this->initFromArray($data) != 3) {
            echo "Warning: Object user was not properly initialized";
        }
        $this->session = $session;
        
    }
    
}

class whitelistItem
        extends dbItem
{
    var $mac;
    var $approver_id;
    var $approver;
    var $approval_time;
    
    
    function whitelistItem($data)
    {
        /*
        echo "Create whitelist from<pre>";
        var_dump($data);
        echo "</pre>";
        */        
        $this->initFromArray($data);
    }
   
}


function logMessage($msg, $me)
{
        global $db;
        $db->execute("insert into log (user_id, message, log_time) values (?, '?', ?)", array($me->id, $msg, time()), $err); 
        if ($err) {
            error("Could not log message: $err", $me, false);
            exit(1);
        }
        
}

function logGet($limit_start=0, $limit_count=20) 
{
    global $db;
    $query = $db->execute("
select log.message as message, log.user_id as user_id, log.log_time as log_time, user.username as username
from log
join user
on log.user_id = user.id
order by log_time desc
limit ? offset ?", array($limit_count, $limit_start), $err); 
    
    if($err) {
        error($err, $me, false);
        return;
    }
    
    $res=array();
    
    while($row = $query->fetch()) {
        $res[] = $row;
    }
    return $res;
}

function logGetCount()
{
    global $db;
    $query = $db->execute("
select count(*) as num
from log", array(), $err);
    
    
    if($err) {
        error($err, $me, false);
        return 0;
    }
    
    if($row = $query->fetch()) {
        return $row['num'];
    }
    return 0;
    
}



class MySQLiteDatabase extends SQLiteDatabase
{
    
    function execute($query, $param=array(), &$error_msg=null)
    {
        $idx = 0;
        $q2 = "";
        
        for ($i=0; $i<strlen($query); $i++) {
            if ($query[$i] == '?') {
                $q2 .= sqlite_escape_string($param[$idx++]);
            }
            else {
                $q2 .= $query[$i];
            }
        }
        
        //echo "Execute query: <pre>$q2</pre>";
        
        return $this->query($q2, SQLITE_BOTH, $error_msg);
    }
    
    
}

function getParam($name, $default=null) 
{
    return array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : $default;
}

function writeHeader($title)
{
    echo '
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
"http://www.w3.org/TR/html4/strict.dtd">
<html>
        <head>
                <meta http-equiv="Content-Type" content="text/html;charset=utf-8
">
                <script>
function popupShow(id) {
        box = document.getElementById(id);
        box.style.display="block";
}

function popupHide(id) {
        box = document.getElementById(id);
        box.style.display="none";
}

                </script>
                <style type="text/css">
body
{
        font-size: 80%;
        font-family: sans-serif;
        background-color: #eee;
}

button 
{
        background-color: #eee;
        border: 1px solid black;
}

.anchor
{
 display: block;
 position:relative;
}


.popup
{
 display:none;
 position: absolute;
 left: 10px;
 top:10 px;
 background: white;
 border: 1px solid black;
 padding: 0px;
}

.popup_content
{
 padding: 5px 15px;
}

.popup_title
{
 background: #aaf;
 padding: 3px;
 
}

.popup_title a
{
 position:absolute;
 right: 0px;
 top: 0px;
 color: black;
 font-family: sans-serif;
 padding: 3px 6px;
 margin:0px;
 
}

.popup_title a:link,
.popup_title a:visited,
.popup_title a:hover 
{
    text-decoration: none;
}


.popup_title a:hover 
{
 background: #bbf;
}

.onmouseover
{
 display:none;
}

.mouseoverowner
{
        position: relative;
}

a:hover .onmouseover
{
        display:block;
        position: absolute; 
        top: 20px; left: 40; 
        width: 300px;
        background: #ffa;
        border: 1px solid black;
        z-index: 1;
        font-size: small;
        padding: 5px 15px;
        
}

th a,
th a:link,
th a:visited,
th a:hover
{
        text-decoration: none;
        color: black;
        text-align: left;
}



.login,
.user_edit,
.log_view,
.message_view,
.whitelist_edit,
.blurb
{
        border:1px solid black;
        margin:10px;
        padding: 10px 15px;
        background-color: white;
}

.login
{
 width:400px;
 position: relative;
 top: 100px;
 left: 100px;
}

.user_edit,
.log_view,
.message_view,
.whitelist_edit
{
            float:left;
}

h1, h2, h3
{
        margin:0px;
        padding: 10px 15px;
        font-family: "serif";
}


.whitelist_edit
{
        clear: left;
}


.blurb
{
        float:right;
}

.error
{
        color: #900;
}


                </style>
                <script type="text/javascript">

function toggleVisible(el, btn)
{
    if(el.style.display=="none") {
        el.style.display = "block";
        btn.innerHTML = "-"
    } else {
        el.style.display = "none";
        btn.innerHTML = "+"
    }
}


function validateUserForm(form, requirePassword)
{
    if (form.elements["new_password"].value != form.elements["new_password2"].value) {
        alert("Passwords don\'t match");
        return false;
    }

    if (requirePassword && form.elements["new_password"].value == "" ){
        alert("No password");
        return false;
    }
    
    if (form.elements["new_password"].value == form.elements["new_username"].value) {
        alert("Bad password");
        return false;
        
    }
    
    if (form.elements["new_username"].value == "" ) {
        alert("Username field is empty");
        return false;
    }
    
    return true;
    
}

function validateMac(mac)
{    
        var re=new RegExp("^[a-fA-F0-9][a-fA-F0-9][:.][a-fA-F0-9][a-fA-F0-9][:.][a-fA-F0-9][a-fA-F0-9][:.][a-fA-F0-9][a-fA-F0-9][:.][a-fA-F0-9][a-fA-F0-9][:.][a-fA-F0-9][a-fA-F0-9]$");
        var res = re.exec(mac);
        if(!res) alert("Bad MAC address");
        return !!res;
}


                </script>
                <title>'.$title.'</title>
        </head>
        <body>

';
    




}


function writeFooter()
{
    echo "</body></html>";
    
}

function checkSession($session) 
{
    global $db;
    $query = $db->execute("
select user.username as username, 
        user.id as id, 
        user.fullname as fullname, 
        session.expiry_time as expiry_time 
from session 
join user 
        on session.user_id = user.id 
where user.deleted=0 and session.id = '?'", array($session));
    if ($err) {
        error($err, null, false);
        return false;
    }
    
    if($row = $query->fetch()) {
        
        if ($row['expiry_time'] > time()) {
            $exp = time() + SESSION_TIMEOUT;
            $db->execute("update session set expiry_time = ? where id = '?'", array($exp, $session));
            if ($err) {
                error($err, null, false);
                return false;
            }
            setCookie("session", $session, $exp);
            
            return new user($row, $session);
        }
        
    }
    else {
        echo "Could not find session $session <br>";
    }
    
    

    return false;
}

function checkPassword($mangled, $real) 
{
    $pass_arr = explode(":", $mangled);
    return sha1($real . $pass_arr[0]) == $pass_arr[1];
}

function makeSalt($count) 
{
    $salt = "";
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    
    for($i=0; $i<$count; $i++) {
        $salt .= $chars[mt_rand(0,strlen($chars))];
    }
    return $salt;
    
}


function manglePassword($password) 
{
    $salt = makeSalt(12);
    //    echo "YAY salt $salt<br>";
    return $salt . ":" . sha1($password . $salt);
}


function createSession($username, $password)
{
    global $db;
    global $logged_in_user;
    
    $query = $db->execute("select * from user where username = '?' and deleted=0", array($username));
    if ($err) {
        error($err, null, false);
        return false;
    }
    
    if($row = $query->fetch()) {
        if (checkPassword($row['password'], $password)) {
            $session = makeSalt(64);
            $exp = time() + SESSION_TIMEOUT;
            $db->execute("insert into session (id, user_id, expiry_time) values ('?', ?, ?)", array($session, $row['id'], $exp));
            setCookie("session", $session, $exp);
            $logged_in_user = new user($row, $session);
            logMessage("User &#8220;$username&#8221; logged in", $logged_in_user);
            return $logged_in_user;
            
        }
    }
    return false;
}

function destroySession($session) 
{
    global $db;
    
    $query = $db->execute("delete from session where id='?'", array($session));
    if ($err) {
        error($err, null, false);
    
        return;
    }
    setCookie("session", "", 0);
}

function redirect($page=null) 
{
    if (!$page) {
        if (messageGet())
            $page = "index.php?message_str=" . urlEncode(messageGet()) ;
        else
            $page = "index.php";

    }
    header("Location: $page");
    exit(0);
}

function whitelistGetCount($filter=null)
{
    global $db;
        $q = "
select count(*) as num
from whitelist
join user 
on whitelist.approver_id = user.id";

    $param=array();
        
    if ($filter) {
        $q .="
where mac like '%?%' or user.username like '%?%'";
        $param[] = $filter;
        $param[] = $filter;
    }
 
    $query = $db->execute($q, $param, $err);

    if($err) {
        error($err, $me, false);
        return 0;
    }
    
    if($row = $query->fetch()) {
        return $row['num'];
    }
    return 0;
    
   
}


function whitelistGet($order='approval_time desc', $filter=null, $limit_start=0, $limit_count=20)
{
    global $db;

    
    //    echo "order $order, filter $filter, limit_start $limit_start, limit_stop $limit_count<br>";
    

    $q = "
select 
        whitelist.mac as mac, 
        whitelist.approver_id as approver_id, 
        whitelist.approval_time as approval_time, 
        user.username as approver
from whitelist
join user 
on whitelist.approver_id = user.id";
    $param=array();
    
    if ($filter) {
        $q .= "
where mac like '%?%' or approver like '%?%'";
        $param[] = $filter;
        $param[] = $filter;
    }
    

    $q .= "
order by $order";
    $q .= "
limit ? offset ?";

    $param[] = $limit_count;
    $param[] = $limit_start;

    $query = $db->execute($q, $param, $err);
    
    if ($err) {
        error($err, null, false);
        return null;
    }
    $whitelist=array();
    
    while($row = $query->fetch()) {
        $whitelist[] = new whitelistItem($row);
    }
        
    return $whitelist;
     
}

$message_str="";

function error($str, $me, $log=true) 
{
    global $message_str;
    if ($log)
        logMessage("Error: $str", $me);
    
    $fmt = "<div class='error'>Error: $str</div>";
    $message_str .= $fmt;
    echo $fmt;
}

function message($str, $me, $log=true) 
{
    global $message_str;
    if ($log)
        logMessage($str, $me);
    
    $fmt = "<div class='message'>$str</div>";
    $message_str .= $fmt;
    echo $fmt;
}

function messageGet()
{
    global $message_str;
    return $message_str;
}

function makeUrl($v1=null, $v2=null) 
{
    if(is_array($v1)) {
        $arr = $v1;
        
    }
    else {
        if($v1===null) {
            $arr = array();
        }
        else {
            $arr = array($v1=>$v2);
        }
    }

    $res = array();
    foreach($arr as $key => $value) 
    {
        if ($value !== null) {
            $res[] = urlEncode($key) . "=" . urlEncode($value);
        }
        
    }
    
    foreach($_GET as $key => $value) 
    {
        if (!array_key_exists($key, $arr) ) {
            $res[] = urlEncode($key) . "=" . urlEncode($value);
        }
    }

    if (count($res) == 0) 
        return "index.php";
        

    return "?" . implode("&", $res);
}


function makePager($page_var, $msg_count) 
{
    $current_page = getParam($page_var, 1);
    $log_count = PAGER_PAGES;
   
    $pages = floor(($msg_count-1)/$log_count)+1;

    if ($pages > 1) {

        if($current_page != '1') {
            $pager .= "<a href='".makeUrl($page_var, null)."'>&#x226a;</a>&nbsp;&nbsp;";
            $pager .= "<a href='".makeUrl(array($page_var=>$current_page-1))."'>&lt;</a>&nbsp;&nbsp;";
        }
        else {
            $pager .= "&#x226a;&nbsp;&nbsp;&lt;&nbsp;&nbsp;";
        }
        
        
        for( $i=1; $i <= $pages; $i++) {
            if($i == $current_page) {
                $pager .= "$i&nbsp;&nbsp;";
            }
            else {
                $pager .= "<a href='".makeUrl(array($page_var=>$i))."'>$i</a>&nbsp;&nbsp;";
            }
            
        }

        if($current_page != $pages) {
            $pager .= "<a href='".makeUrl(array($page_var=>$current_page+1))."'>&gt;</a>&nbsp;&nbsp;";
            $pager .= "<a href='".makeUrl(array($page_var=>$pages))."'>&#x226b;</a>&nbsp;&nbsp;";
        }
        else {
            $pager .= "&gt;&nbsp;&nbsp;&#x226b;&nbsp;&nbsp;";
        }
    }
    return $pager;
}


function whitelistAdd($mac, $me) 
{
    if (!$mac) {
        return;
    }
    
    global $db;
    
    $query = $db->execute("insert into whitelist (mac, approver_id, approval_time) values ('?', ?, ?)", array($mac, $me->id, time()), $err); 
    if ($err) {
        error("Failed to add MAC address &#8220;$mac&#8221;: $err", $me);
        return false;
    }

    message("MAC address &#8220;$mac&#8221; was added", $me);

    return true;
    
}

function whitelistRemove($mac, $me) 
{
    if (!$mac) {
        error("No mac address specified", $me, false);
        return false;
    }
    
    global $db;
    
    $query = $db->execute("delete from whitelist where mac='?'", array($mac), $err); 
    if ($err) {
        error("Could not remove MAC &#8220;$mac&#8221;. Reason: $err", $me);
        return false;
    }
    message("MAC &#8220;$mac&#8221; was removed", $me);
    
    return true;
    
}

function userGet()
{
    global $db;
    
    $query = $db->execute("
select 
        *
from user
where deleted=0
order by username");
    if ($err) {
        error($err, null, false);
        return null;
    }
    $user=array();
    
    while($row = $query->fetch()) {
        $user[] = new user($row, null);
    }
        
    return $user;
     
}


function userAdd($username, $password, $fullname, $me) 
{
    global $db;

    $q1 = $db->execute("select id from user where username='?' and deleted=0", 
                       array($username), 
                       $err);
    if ($err) {
        error($err, null, false);
        return false;
    }

    if($row = $q1->fetch()) {
        error("Failed to create user &#8220;$username&#8221;. User already exists.", $me);
        return false;
        
    }
    
    $query = $db->execute("insert into user (username, password, fullname, deleted) values ('?', '?', '?', 0)", 
                          array($username, manglePassword($password), $fullname), 
                          $err); 
    if ($err) {
        error("Failed to create user &#8220;$username&#8221; reason: $err", $me);
        return false;
    }

    message("User &#8220;$username&#8221; created", $me);

    return true;
        
}

function userEdit($id, $username, $password, $fullname, $me) 
{
    global $db;

    $q1 = $db->execute("select id from user where id='?' and deleted=0", 
                       array($id), 
                       $err);
    if ($err) {
        error("Failed to edit user &#8220;$username&#8221;, reason: $err", $me);
        return false;
    }
    if(! $q1->fetch()) {
        error("Failed to edit user &#8220;$username&#8221;, user doesn't exist.", $me);
        return false;
    }

    $q1 = $db->execute("select id from user where username='?' and deleted=0", 
                       array($username), 
                       $err);
    if ($err) {
        error("Failed to edit user &#8220;$username&#8221;, reason: $err", $me);
        return false;
    }
   
    if(($row = $q1->fetch()) && ($row['id'] != $id)) {
        error($row['id']." != $id Could not rename user to &#8220;$username&#8221; - user already exists.", $me);
        return false;
    }

    if ($password)
        $q1 = $db->execute("update user set username='?', password='?', fullname='?' where id=? and deleted=0", 
                           array($username, manglePassword($password), $fullname, $id), 
                           $err);
    else
        $q1 = $db->execute("update user set username='?', fullname='?' where id=? and deleted=0", 
                           array($username, $fullname, $id), 
                           $err);
    if ($err) {
        error("Failed to edit user &#8220;$username&#8221;, reason: $err", $me);
        return false;
    }

    message("User &#8220;$username&#8221; was edited", $me);

    return true;
        
}

function userRemove($id, $me) 
{
    if (!$id) {
        return;
    }
    
    global $db;

    $q1 = $db->execute("select username from user where id='?' and deleted=0", 
                       array($id), 
                       $err);
    if ($err) {
        error("Failed to remove user with id $id, reason: $err", $me);
        return false;
    }
    if(! $row=$q1->fetch()) {
        error("Failed to remove user with id $id, user doesn't exist.", $me);
        return false;
    }
    $username = $row['username'];
    

    
    $query = $db->execute("update user set deleted=1 where id=?", array($id), $err); 
    if ($err) {
        error("Failed to remove user &#8220;$username&#8221;. Reason: $err", $me);
        return false;
    }
    message("Removed user &#8220;$username&#8221;", $me);
    return true;
    
}

function stripslashes_deep($value)
{
    $value = is_array($value) ?
                array_map('stripslashes_deep', $value) :
                stripslashes($value);

    return $value;
}

function check_magic_quotes() 
{
    if (get_magic_quotes_gpc()) {
        
        $_REQUEST = stripslashes_deep($_REQUEST);
        $_GET = stripslashes_deep($_GET);
        $_POST = stripslashes_deep($_POST);
    }
    
}

function utilInit()
{
    global $whitelist;
    global $db;
    
    check_magic_quotes();
    

    $db = new MySQLiteDatabase(DB_FILE, 0666, $err);
    if (!$db) {
        error($err, null, false);
        return false;
    }
    /*
    $db->execute("drop table whitelist");
    $db->execute("drop table user");
    $db->execute("drop table session");
    */

    /*
        Check that the correct tables exist, and create them
        otherwise. We do this to avoif having to run any special
        schema creation script during installation. It also means the
        script is slightly more easy to move around between machines.
    */

    $query = $db->execute("select name from sqlite_master where type='table'", array(), $err); 
    if ($err) {
        error($err, null, false);
        return false;
    }

    $tables = array();
    
    while ($row = $query->fetch())
        $tables[$row['name']] = $row;

    $query = $db->execute("select name from sqlite_master where type='index'", array(), $err); 
    if ($err) {
        error($err, null, false);
        return false;
    }

    $indices = array();
    
    while ($row = $query->fetch())
        $indices[$row['name']] = $row;


    if (!array_key_exists("user", $tables)) {
        $db->execute("
create table user 
(
        id integer primary key not null, 
        username varchar(64) not null, 
        fullname varchar(64) not null, 
        password varchar(64) not null,
        deleted integer not null
)", array(), $err); 
        if ($err) {
            error($err, null, false);
            return false;
        }

        $db->execute("
insert into user 
(
        username, fullname, password, deleted
) 
values
(
        '?', '?', '?', ?
)", array("admin", "Default administrator account", manglePassword("admin"), 0));
        
    }
    
    if (!array_key_exists("whitelist", $tables)) {
        $db->execute("
create table whitelist 
(
        mac varchar(64) not null unique, 
        approver_id int not null references user (id) on delete restrict, 
        approval_time int not null
)", array(), $err); 
        if ($err) {
            error($err, null, false);
            return false;
        }
    }
    
    if (!array_key_exists("session", $tables)) {
        $db->execute("
create table session 
(
        id varchar(64) not null, 
        expiry_time int not null, 
        user_id int not null references user (id) on delete cascade
)", array(), $err); 
        if ($err) {
            error($err, null, false);
            return false;
        }
    }
    
    
    if (!array_key_exists("log", $tables)) {
        $db->execute("
create table log
(
        user_id int not null references user (id) on delete cascade,
        log_time int not null,
        message varchar(4096) not null
)", array(), $err); 
        if ($err) {
            error($err, null, false);
            return false;
        }
    }
  
    if (!array_key_exists("log_time_idx", $indices)) {
        $db->execute("create index log_time_idx on log(log_time)", array(), $err); 
        if ($err) {
            error($err, null, false);
            return false;
        }
    }
    if (!array_key_exists("whitelist_time_idx", $indices)) {
        $db->execute("create index whitelist_time_idx on whitelist(approval_time)", array(), $err); 
        if ($err) {
            error($err, null, false);
            return false;
        }
    }
  
    return true;
    
}

utilInit() || die("<br>Error during init; bailing out...</br>");

?>