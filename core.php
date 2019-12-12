<?php 
namespace {
define('DS', DIRECTORY_SEPARATOR);
define('LIB_PATH', ROOT_PATH . DS . 'lib');
define('CORE_PATH', ROOT_PATH . DS . 'lib/_lib');
if (!defined('VPATH')) {
define('VPATH', getenv('VENDOR_PATH') ? getenv('VENDOR_PATH') : ROOT_PATH);
}
require_once VPATH . DS . '/vendor/autoload.php';
use Symfony\Component\ClassLoader\ClassLoader;
$a = new ClassLoader();
$a->register();
$a->addPrefix('', array(LIB_PATH, CORE_PATH));
}
namespace {
class YamlUserLoader extends \Symfony\Component\Config\Loader\FileLoader
{
public function load($b, $c = null)
{
$d = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($b));
return $d;
}
public function supports($b, $c = null)
{
return is_string($b) && 'yml' === pathinfo($b, PATHINFO_EXTENSION);
}
}
class cfg
{
private static $_yamlFiles = array();
private static $_yamlDatas = array();
private static $_locator = null;
public static function load($e = 'config.yml')
{
if (endWith($e, '.yml')) {
$e = substr($e, 0, -4);
}
if (!isset(self::$_yamlFiles[$e])) {
self::tryLoad($e);
}
if (!isset(self::$_yamlDatas[$e])) {
$f = self::getLocator();
$g = new \Symfony\Component\Config\Loader\LoaderResolver(array(new YamlUserLoader($f)));
$h = new \Symfony\Component\Config\Loader\DelegatingLoader($g);
if (isset(self::$_yamlFiles[$e])) {
$i = self::$_yamlFiles[$e];
self::$_yamlDatas[$e] = $h->load($i);
} else {
debug("cfg: {$e} load fail!");
return [];
}
}
return self::$_yamlDatas[$e];
}
public static function tryLoad($e)
{
$j = ['dev', 'prod', ''];
foreach ($j as $k => $l) {
$m = $e;
if ($l) {
$m .= ".{$l}";
}
$i = self::loadCfg($m . '.yml');
if ($i) {
self::$_yamlFiles[$e] = $i;
return;
}
}
}
public static function loadCfg($e)
{
try {
$n = self::getLocator()->locate($e, null, false);
if ($n) {
return $n[0];
}
} catch (Exception $e) {
}
return null;
}
public static function getLocator()
{
if (!self::$_locator) {
$o = array(ROOT_PATH . '/cfg');
self::$_locator = new \Symfony\Component\Config\FileLocator($o);
}
return self::$_locator;
}
public static function get($k = 'name', $e = 'config.yml')
{
$m = cfg::load($e);
if ($m && isset($m[$k])) {
return $m[$k];
} else {
return null;
}
}
public static function config($k = null)
{
$p = new \Symfony\Component\DependencyInjection\ContainerBuilder();
$o = array(ROOT_PATH . '/cfg/config');
$a = new \Symfony\Component\DependencyInjection\Loader\YamlFileLoader($p, new \Symfony\Component\Config\FileLocator($o));
if (folder_exist($o[0])) {
$q = \Lead\Dir\Dir::scan($o[0], ['type' => 'file']);
foreach ($q as $r) {
debug("load config: {$r}");
$a->load($r);
}
}
$p->compile();
$s = $p->getParameterBag()->all();
if ($k) {
$t = new \core\Dot($s);
$u = $t[$k];
} else {
$u = $s;
}
$v = $p->resolveEnvPlaceholders($u, true);
return $v;
}
public static function get_db_cfg($k = 'use_db')
{
$w = self::get($k, 'db.yml');
return self::get($w, 'db.yml');
}
public static function get_rest_prefix()
{
$x = \cfg::get('rest_prefix');
return $x ? $x : '/rest';
}
public static function get_redis_cfg()
{
$y = 'redis';
if (is_docker_env()) {
$y = 'docker_redis';
}
return self::get($y, 'db.yml');
}
public static function rest($k)
{
return self::get($k, 'rest');
}
}
class ctx
{
private static $_app = null;
private static $_user = null;
private static $_data = null;
private static $_token = null;
private static $_page = null;
private static $_user_tbl = null;
private static $_uripath = null;
private static $_foundRoute = null;
private static $_rest_prefix = null;
private static $_rest_select_add = '';
private static $_rest_join_add = '';
private static $_rest_extra_data = null;
private static $_global_view_data = null;
private static $_gets = array();
public static function init($z)
{
debug(" -- ctx init -- ");
self::$_user_tbl = \cfg::get('user_tbl_name');
self::$_token = self::getToken($z);
self::$_user = self::getTokenUser(self::$_user_tbl, $z);
self::$_rest_prefix = \cfg::get_rest_prefix();
self::$_uripath = uripath($z);
$ab = self::getReqData();
if (!self::isAdminRest() && self::$_user) {
$ab['uid'] = getArg(self::$_user, 'id');
}
$ab['_uptm'] = date('Y-m-d H:i:s');
$ab['uniqid'] = uniqid();
self::$_data = $ab;
$ac = get('page', 1);
$ad = $ac <= 1 ? 1 : $ac - 1;
$ae = $ac + 1;
$af = get('size', 10);
$ag = ($ac - 1) * $af;
$ac = ['page' => $ac, 'prepage' => $ad, 'nextpage' => $ae, 'pagesize' => $af, 'offset' => $ag, 'list' => [], 'totalpage' => 0, 'count' => 0, 'isFirstPage' => false, 'isLastPage' => false];
self::$_page = $ac;
}
public static function app($app = null)
{
if ($app) {
self::$_app = $app;
}
return self::$_app;
}
public static function container()
{
return self::app()->getContainer();
}
public static function req()
{
return self::container()->request;
}
public static function router()
{
return self::container()->router;
}
public static function route_list()
{
$ah = ctx::router()->getRoutes();
$ai = [];
foreach ($ah as $k => $aj) {
$ai[] = ['methods' => $aj->getMethods(), 'name' => $aj->getname(), 'pattern' => $aj->getPattern(), 'groups' => $aj->getgroups(), 'arguments' => $aj->getarguments()];
}
return $ai;
}
public static function logger()
{
return self::container()['logger'];
}
public static function user($ak = null)
{
if ($ak) {
self::$_user = $ak;
}
return self::$_user;
}
public static function uid()
{
if (self::user()) {
return self::user()['id'];
} else {
return 0;
}
}
public static function roles()
{
$ak = self::user();
return isset($ak['roles']) ? $ak['roles'] : [];
}
public static function isRest()
{
return startWith(self::$_uripath, self::$_rest_prefix);
}
public static function isAdmin()
{
$al = is_array(self::roles()) && in_array('admin', self::roles());
debug("is_admin: {$al}");
return $al;
}
public static function isAdminRest()
{
return startWith(self::$_uripath, self::$_rest_prefix . '_admin');
}
public static function retType()
{
$c = '';
$am = self::getReqData();
if (isset($am['ret-type'])) {
$c = $am['ret-type'];
}
return $c;
}
public static function isRetJson()
{
return self::retType() == 'json';
}
public static function appMode()
{
$m = \cfg::get('app');
$an = isset($m['mode']) ? $m['mode'] : 'normal';
info("app_mode {$an}");
return $an;
}
public static function isStateless()
{
return self::appMode() == 'stateless';
}
public static function isEnableSso()
{
$m = \cfg::get('sso');
return getArg($m, 'enable');
}
public static function uri()
{
return self::$_uripath;
}
public static function data($ab = null)
{
if ($ab) {
self::$_data = $ab;
}
$ao = getArg(self::$_data, 'data_key');
if ($ao) {
return self::$_data[$ao];
}
return self::$_data;
}
public static function src_data($ao = null)
{
$ab = dissoc(self::$_data, ['_uptm', 'uniqid', 'token', 'uid']);
return $ab;
}
public static function pageinfo()
{
return self::$_page;
}
public static function page()
{
return self::$_page['page'];
}
public static function pagesize($ap = null)
{
if ($ap) {
self::$_page['pagesize'] = $ap;
}
return self::$_page['pagesize'];
}
public static function offset()
{
return self::$_page['offset'];
}
public static function count($aq)
{
self::$_page['count'] = $aq;
$ar = $aq / self::$_page['pagesize'];
$ar = ceil($ar);
self::$_page['totalpage'] = $ar;
if (self::$_page['page'] == '1') {
self::$_page['isFirstPage'] = true;
}
if (!$ar || self::$_page['page'] == $ar) {
self::$_page['isLastPage'] = true;
}
if (self::$_page['nextpage'] > $ar) {
self::$_page['nextpage'] = $ar ? $ar : 1;
}
$as = self::$_page['page'] - 4;
$at = self::$_page['page'] + 4;
if ($at > $ar) {
$at = $ar;
$as = $as - ($at - $ar);
}
if ($as <= 1) {
$as = 1;
}
if ($at - $as < 8 && $at < $ar) {
$at = $as + 8;
}
$at = $at ? $at : 1;
$ai = range($as, $at);
self::$_page['list'] = $ai;
return self::$_page['count'];
}
public static function limit()
{
return [self::offset(), self::pagesize()];
}
public static function getReqData()
{
$z = req();
return $z->getParams();
$au = isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : '';
$au = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : $au;
$ab = '';
if (!empty($_POST)) {
$ab = $_POST;
} else {
$av = file_get_contents("php://input");
if ($av) {
if (strpos($au, 'application/x-www-form-urlencoded') !== false) {
parse_str($av, $aw);
$ab = $aw;
} else {
if (strpos($au, 'application/json') !== false) {
$ab = json_decode($av, true);
}
}
}
}
return $ab;
}
public static function getToken($z, $k = 'token')
{
$ax = $z->getParam($k);
$ab = $z->getParams();
if (!$ax) {
$ax = getArg($ab, $k);
}
if (!$ax) {
$ax = getArg($_COOKIE, $k);
}
return $ax;
}
public static function token()
{
return self::$_token;
}
public static function getUcTokenUser($ax)
{
if (!$ax) {
return null;
}
$ay = cache($ax);
$az = isset($ay['userinfo']) ? $ay['userinfo'] : null;
if (isset($ay['luser'])) {
$az['id'] = $az['uid'] = $ay['luser']['id'];
return $az;
}
return null;
}
public static function getCubeTokenUser($ax)
{
if (!$ax) {
return null;
}
$ay = cache($ax);
$ak = $ay['user'] ? $ay['user'] : null;
if (isset($ay['luser'])) {
$ak['id'] = $ak['uid'] = $ay['luser']['id'];
return $ak;
}
return null;
}
public static function getAuthType($bc, $ax)
{
if (isset($bc['auth_type'])) {
return $bc['auth_type'];
}
$bd = explode('$$', $ax);
if (count($bd) == 2) {
return $bd[1];
}
return null;
}
public static function getTokenUser($be, $z)
{
$bf = $z->getParam('uid');
$ak = null;
$bc = $z->getParams();
$bg = self::check_appid($bc);
if ($bg && check_sign($bc, $bg)) {
debug("appkey: {$bg}");
$ak = ['id' => $bf, 'roles' => ['admin']];
} else {
if (self::isStateless()) {
debug("isStateless");
$ak = ['id' => $bf, 'role' => 'user'];
} else {
$ax = self::$_token;
$bh = \cfg::get('use_ucenter_oauth');
if ($bi = self::getAuthType($bc, $ax)) {
if ($bi == 'cube') {
return self::getCubeTokenUser($ax);
}
}
if ($bh) {
return self::getUcTokenUser($ax);
}
$bj = self::getToken($z, 'access_token');
if (self::isEnableSso()) {
debug("getTokenUserBySso");
$ak = self::getTokenUserBySso($ax);
} else {
debug("get from db");
if ($ax) {
$bk = \cfg::get('disable_cache_user');
if ($bk) {
$ak = \db::row($be, ['token' => $ax]);
} else {
$ak = cache($ax);
$ak = $ak['user'];
}
} else {
if ($bj) {
$ak = self::getAccessTokenUser($be, $bj);
}
}
if ($ak) {
$ak['roles'] = [getArg($ak, 'role')];
}
}
}
}
return $ak;
}
public static function check_appid($bc)
{
$bl = getArg($bc, 'appid');
if ($bl) {
$m = cfg::get('support_service_list', 'service');
if (isset($m[$bl])) {
debug("appid: {$bl} ok");
return $m[$bl];
}
}
debug("appid: {$bl} not ok");
return '';
}
public static function getTokenUserBySso($ax)
{
$ak = ms('sso')->getuserinfo(['token' => $ax])->json();
return $ak;
}
public static function getAccessTokenUser($be, $bj)
{
$bm = \db::row('oauth_access_tokens', ['access_token' => $bj]);
if ($bm) {
$bn = strtotime($bm['expires']);
if ($bn - time() > 0) {
$ak = \db::row($be, ['id' => $bm['user_id']]);
}
}
return $ak;
}
public static function user_tbl($bo = null)
{
if ($bo) {
self::$_user_tbl = $bo;
}
return self::$_user_tbl;
}
public static function render($bp, $bq, $br, $ab)
{
$bs = new \Slim\Views\Twig($bq, ['cache' => false]);
self::$_foundRoute = true;
return $bs->render($bp, $br, $ab);
}
public static function isFoundRoute()
{
return self::$_foundRoute;
}
public static function rest_prefix()
{
return self::$_rest_prefix;
}
public static function parse_rest()
{
$bt = str_replace(self::$_rest_prefix, '', self::uri());
$bu = explode('/', $bt);
$bv = getArg($bu, 1, '');
$bw = getArg($bu, 2, '');
return [$bv, $bw];
}
public static function rest_select_add($bx = '')
{
if ($bx) {
self::$_rest_select_add = $bx;
}
return self::$_rest_select_add;
}
public static function rest_join_add($bx = '')
{
if ($bx) {
self::$_rest_join_add = $bx;
}
return self::$_rest_join_add;
}
public static function rest_extra_data($ab = '')
{
if ($ab) {
self::$_rest_extra_data = $ab;
}
return self::$_rest_extra_data;
}
public static function global_view_data($ab = '')
{
if ($ab) {
self::$_global_view_data = $ab;
}
return self::$_global_view_data;
}
public static function gets($by = '', $bz = '')
{
if (!$by) {
return self::$_gets;
}
if (!$bz) {
return self::$_gets[$by];
}
if ($bz == '_clear') {
$bz = '';
}
self::$_gets[$by] = $bz;
return self::$_gets;
}
}
class cube
{
static $passport = null;
static $client = null;
static $cid = null;
static $cst = null;
public static function client()
{
if (!self::$client) {
$m = cfg::get('oauth', 'oauth')['cube'];
$cd = $m['endpoint'];
$ce = new \Gql\Client($cd, ['verify' => false]);
self::$client = $ce;
self::$cid = $m['client_id'];
self::$cst = $m['client_secret'];
}
return self::$client;
}
public static function login($cf, $cg)
{
self::client();
$ch = ['args' => ['client_id' => self::$cid, 'client_secret' => self::$cst, 'username' => $cf, 'password' => $cg], "resp" => ["access_token", "token_type", 'expires_in', 'refresh_token']];
$ab = self::client()->query('passport', $ch);
$ab = $ab['data'];
self::$passport = $ab['passport'];
return $ab;
}
public static function openid_login($ci, $cj = 'wechat')
{
self::client();
$ch = ['args' => ['client_id' => self::$cid, 'client_secret' => self::$cst, 'openid' => $ci, 'bind_type' => $cj], "resp" => ["access_token", "token_type", 'expires_in', 'refresh_token']];
$ab = self::client()->query('passport', $ch);
$ab = $ab['data'];
self::$passport = $ab['passport'];
return $ab;
}
public static function user()
{
$ab = self::client()->query('user', ['params' => ['access_token' => self::$passport['access_token']], 'resp' => ['id', 'username', 'email', 'phone', 'roles' => ['id', 'app_id', 'instance_id', 'role_id']]]);
$ab = $ab['data'];
return $ab;
}
public static function roles()
{
$ab = self::client()->query('roles', ['params' => ['access_token' => self::$passport['access_token']], 'resp' => ['id', 'app_id', 'instance_id', 'name', 'title', 'description']]);
$ab = $ab['data'];
return $ab;
}
public static function modules()
{
$ab = self::client()->query('modules', ['params' => ['access_token' => self::$passport['access_token']], 'resp' => ['id', 'app_id', 'type', 'name', 'url']]);
$ab = $ab['data'];
return $ab;
}
}
use Medoo\Medoo;
if (!function_exists('fixfn')) {
function fixfn($ck)
{
foreach ($ck as $cl) {
if (!function_exists($cl)) {
eval("function {$cl}(){}");
}
}
}
}
if (!class_exists('cfg')) {
class cfg
{
public static function get_db_cfg()
{
return array('database_type' => 'mysql', 'database_name' => 'myapp_dev', 'server' => 'mysql', 'username' => 'root', 'password' => '123456', 'charset' => 'utf8');
}
}
}
$ck = array('debug');
fixfn($ck);
class db
{
private static $_db_list;
private static $_db_default;
private static $_db_master;
private static $_dbc_list;
private static $_db;
private static $_dbc;
private static $_dbc_master;
private static $_ins;
private static $tbl_desc = array();
public static function init($m, $cm = true)
{
self::init_db($m, $cm);
}
public static function conns()
{
$cn['_db'] = self::queryRow('select user() as user, database() as dbname');
self::use_master_db();
$cn['_db_master'] = self::queryRow('select user() as user, database() as dbname');
self::use_default_db();
$cn['_db_default'] = self::queryRow('select user() as user, database() as dbname');
return $cn;
}
public static function new_db($m)
{
if (isset($m['port']) && !$m['port']) {
$m['port'] = '3306';
}
return new Medoo($m);
}
public static function get_db_cfg($m = 'use_db')
{
$co = \cfg::get('dbc_type', 'db.yml');
if (!$co) {
$co = 'default';
}
if ($co == 'default') {
if (is_string($m)) {
$m = \cfg::get_db_cfg($m);
}
$m['database_name'] = env('DB_NAME', $m['database_name']);
$m['server'] = env('DB_HOST', $m['server']);
$m['username'] = env('DB_USER', $m['username']);
$m['password'] = env('DB_PASS', $m['password']);
} else {
if ($co == 'domain') {
$cp = self::get_dbc_domain_key();
$cq = self::get_dbc_domain_map();
$m = $cq[$cp];
if (!$m) {
ret(1, 'domain key error');
}
}
}
return $m;
}
public static function get_dbc_domain_key()
{
$k = get('dk');
if (!$k) {
if (isset($_SERVER['HTTP_HOST'])) {
$k .= $_SERVER['HTTP_HOST'];
}
}
return $k;
}
public static function get_dbc_domain_map()
{
$cr = cache('dbc_domain_map', function () {
$cs = \cfg::get('dbc_domain_api', 'db.yml');
$ct = file_get_contents($cs);
$ab = json_decode($ct, true);
return $ab['data'];
}, 600);
return $cr;
}
public static function init_db($m, $cm = true)
{
self::$_dbc = self::get_db_cfg($m);
$cu = self::$_dbc['database_name'];
self::$_dbc_list[$cu] = self::$_dbc;
self::$_db_list[$cu] = self::new_db(self::$_dbc);
if ($cm) {
self::use_db($cu);
}
}
public static function use_db($cu)
{
self::$_db = self::$_db_list[$cu];
self::$_dbc = self::$_dbc_list[$cu];
}
public static function use_default_db()
{
self::$_db = self::$_db_default;
}
public static function use_master_db()
{
self::$_db = self::$_db_master;
}
public static function dbc()
{
return self::$_dbc;
}
public static function switch_dbc($cv)
{
$cw = ms('master')->get(['path' => '/admin/corpins', 'data' => ['corpid' => $cv]]);
$cx = $cw->json();
$cx = getArg($cx, 'data', []);
self::$_dbc = $cx;
self::$_db = self::$_db_default = self::new_db(self::$_dbc);
}
public static function obj()
{
if (!self::$_db) {
self::$_dbc = self::$_dbc_master = self::get_db_cfg();
self::$_db = self::$_db_default = self::$_db_master = self::new_db(self::$_dbc);
info('====== init dbc =====');
$ax = \ctx::getToken(req());
$ak = \ctx::getUcTokenUser($ax);
$cv = getArg($ak, 'corpid');
if ($cv) {
self::switch_dbc($cv);
}
}
return self::$_db;
}
public static function db_type()
{
if (!self::$_dbc) {
self::obj();
}
return self::$_dbc['database_type'];
}
public static function desc_sql($cy)
{
if (self::db_type() == 'mysql') {
return "desc `{$cy}`";
} else {
if (self::db_type() == 'pgsql') {
return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$cy}'";
} else {
return '';
}
}
}
public static function table_cols($bv)
{
$cz = self::$tbl_desc;
if (!isset($cz[$bv])) {
$de = self::desc_sql($bv);
if ($de) {
$cz[$bv] = self::query($de);
self::$tbl_desc = $cz;
debug("---------------- cache not found : {$bv}");
} else {
debug("empty desc_sql for: {$bv}");
}
}
if (!isset($cz[$bv])) {
return array();
} else {
return self::$tbl_desc[$bv];
}
}
public static function col_array($bv)
{
$df = function ($bz) use($bv) {
return $bv . '.' . $bz;
};
return getKeyValues(self::table_cols($bv), 'Field', $df);
}
public static function valid_table_col($bv, $dg)
{
$dh = self::table_cols($bv);
foreach ($dh as $di) {
if ($di['Field'] == $dg) {
$c = $di['Type'];
return is_string_column($di['Type']);
}
}
return false;
}
public static function tbl_data($bv, $ab)
{
$dh = self::table_cols($bv);
$v = [];
foreach ($dh as $di) {
$dj = $di['Field'];
if (isset($ab[$dj])) {
$v[$dj] = $ab[$dj];
}
}
return $v;
}
public static function test()
{
$de = "select * from tags limit 10";
$dk = self::obj()->query($de)->fetchAll(\PDO::FETCH_ASSOC);
var_dump($dk);
}
public static function has_st($bv, $dl)
{
$dm = '_st';
return isset($dl[$dm]) || isset($dl[$bv . '.' . $dm]);
}
public static function getWhere($bv, $dn)
{
$dm = '_st';
if (!self::valid_table_col($bv, $dm)) {
return $dn;
}
$dm = $bv . '._st';
if (is_array($dn)) {
$do = array_keys($dn);
$dp = preg_grep("/^AND\\s*#?\$/i", $do);
$dq = preg_grep("/^OR\\s*#?\$/i", $do);
$dr = array_diff_key($dn, array_flip(explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')));
$dl = [];
if ($dr != array()) {
$dl = $dr;
if (!self::has_st($bv, $dl)) {
$dn[$dm] = 1;
$dn = ['AND' => $dn];
}
}
if (!empty($dp)) {
$l = array_values($dp);
$dl = $dn[$l[0]];
if (!self::has_st($bv, $dl)) {
$dn[$l[0]][$dm] = 1;
}
}
if (!empty($dq)) {
$l = array_values($dq);
$dl = $dn[$l[0]];
if (!self::has_st($bv, $dl)) {
$dn[$l[0]][$dm] = 1;
}
}
if (!isset($dn['AND']) && !self::has_st($bv, $dl)) {
$dn['AND'][$dm] = 1;
}
}
return $dn;
}
public static function all_sql($bv, $dn = array(), $ds = '*', $dt = null)
{
$cr = [];
if ($dt) {
$de = self::obj()->selectContext($bv, $cr, $dt, $ds, $dn);
} else {
$de = self::obj()->selectContext($bv, $cr, $ds, $dn);
}
return $de;
}
public static function all($bv, $dn = array(), $ds = '*', $dt = null)
{
$dn = self::getWhere($bv, $dn);
info($dn);
if ($dt) {
$dk = self::obj()->select($bv, $dt, $ds, $dn);
} else {
$dk = self::obj()->select($bv, $ds, $dn);
}
return $dk;
}
public static function count($bv, $dn = array('_st' => 1))
{
$dn = self::getWhere($bv, $dn);
return self::obj()->count($bv, $dn);
}
public static function row_sql($bv, $dn = array(), $ds = '*', $dt = '')
{
return self::row($bv, $dn, $ds, $dt, true);
}
public static function row($bv, $dn = array(), $ds = '*', $dt = '', $du = null)
{
$dn = self::getWhere($bv, $dn);
if (!isset($dn['LIMIT'])) {
$dn['LIMIT'] = 1;
}
if ($dt) {
if ($du) {
return self::obj()->selectContext($bv, $dt, $ds, $dn);
}
$dk = self::obj()->select($bv, $dt, $ds, $dn);
} else {
if ($du) {
return self::obj()->selectContext($bv, $ds, $dn);
}
$dk = self::obj()->select($bv, $ds, $dn);
}
if ($dk) {
return $dk[0];
} else {
return null;
}
}
public static function one($bv, $dn = array(), $ds = '*', $dt = '')
{
$dv = self::row($bv, $dn, $ds, $dt);
$dw = '';
if ($dv) {
$dx = array_keys($dv);
$dw = $dv[$dx[0]];
}
return $dw;
}
public static function parseUk($bv, $dy, $ab)
{
$dz = true;
info("uk: {$dy}, " . json_encode($ab));
if (is_array($dy)) {
foreach ($dy as $ef) {
if (!isset($ab[$ef])) {
$dz = false;
} else {
$eg[$ef] = $ab[$ef];
}
}
} else {
if (!isset($ab[$dy])) {
$dz = false;
} else {
$eg = [$dy => $ab[$dy]];
}
}
$eh = false;
if ($dz) {
info("has uk {$dz}");
info("where: " . json_encode($eg));
if (!self::obj()->has($bv, ['AND' => $eg])) {
$eh = true;
}
} else {
$eh = true;
}
return [$eg, $eh];
}
public static function save($bv, $ab, $dy = 'id')
{
list($eg, $eh) = self::parseUk($bv, $dy, $ab);
info("isInsert: {$eh}, {$bv} {$dy} " . json_encode($ab));
if ($eh) {
debug("insert {$bv} : " . json_encode($ab));
$ei = self::obj()->insert($bv, $ab);
$ab['id'] = self::obj()->id();
} else {
debug("update {$bv} " . json_encode($eg));
$ei = self::obj()->update($bv, $ab, ['AND' => $eg]);
}
if ($ei->errorCode() !== '00000') {
info($ei->errorInfo());
}
return $ab;
}
public static function update($bv, $ab, $dn)
{
self::obj()->update($bv, $ab, $dn);
}
public static function exec($de)
{
return self::obj()->exec($de);
}
public static function query($de)
{
return self::obj()->query($de)->fetchAll(\PDO::FETCH_ASSOC);
}
public static function queryRow($de)
{
$dk = self::query($de);
if ($dk) {
return $dk[0];
} else {
return null;
}
}
public static function queryOne($de)
{
$dv = self::queryRow($de);
return self::oneVal($dv);
}
public static function oneVal($dv)
{
$dw = '';
if ($dv) {
$dx = array_keys($dv);
$dw = $dv[$dx[0]];
}
return $dw;
}
public static function updateBatch($bv, $ab, $dy = 'id')
{
$ej = $bv;
if (!is_array($ab) || empty($ej)) {
return FALSE;
}
$de = "UPDATE `{$ej}` SET";
foreach ($ab as $bw => $dv) {
foreach ($dv as $k => $u) {
$ek[$k][] = "WHEN {$bw} THEN {$u}";
}
}
foreach ($ek as $k => $u) {
$de .= ' `' . trim($k, '`') . '`=CASE ' . $dy . ' ' . join(' ', $u) . ' END,';
}
$de = trim($de, ',');
$de .= ' WHERE ' . $dy . ' IN(' . join(',', array_keys($ab)) . ')';
return self::query($de);
}
}
class fcache
{
const FILE_LIFE_KEY = 'FILE_LIFE_KEY';
const CLEAR_ALL_KEY = 'CLEAR_ALL';
static $_instance = null;
protected $_options = array('cache_dir' => './cache', 'file_locking' => true, 'file_name_prefix' => 'cache', 'cache_file_umask' => 0777, 'file_life' => 100000);
static function &getInstance($el = array())
{
if (self::$_instance === null) {
self::$_instance = new self($el);
}
return self::$_instance;
}
static function &setOptions($el = array())
{
return self::getInstance($el);
}
private function __construct($el = array())
{
if ($this->_options['cache_dir'] !== null) {
$bq = rtrim($this->_options['cache_dir'], '/') . '/';
$this->_options['cache_dir'] = $bq;
if (!is_dir($this->_options['cache_dir'])) {
mkdir($this->_options['cache_dir'], 0777, TRUE);
}
if (!is_writable($this->_options['cache_dir'])) {
exit('file_cache: 路径 "' . $this->_options['cache_dir'] . '" 不可写');
}
} else {
exit('file_cache: "options" cache_dir 不能为空 ');
}
}
static function setCacheDir($l)
{
$em =& self::getInstance();
if (!is_dir($l)) {
exit('file_cache: ' . $l . ' 不是一个有效路径 ');
}
if (!is_writable($l)) {
exit('file_cache: 路径 "' . $l . '" 不可写');
}
$l = rtrim($this->_options['cache_dir'], '/') . '/';
$em->_options['cache_dir'] = $l;
}
static function save($ab, $bw = null, $en = null)
{
$em =& self::getInstance();
if (!$bw) {
if ($em->_id) {
$bw = $em->_id;
} else {
exit('file_cache:save() id 不能为空!');
}
}
$eo = time();
if ($en) {
$ab[self::FILE_LIFE_KEY] = $eo + $en;
} elseif ($en != 0) {
$ab[self::FILE_LIFE_KEY] = $eo + $em->_options['file_life'];
}
$r = $em->_file($bw);
$ab = "\n" . " // mktime: " . $eo . "\n" . " return " . var_export($ab, true) . "\n?>";
$cw = $em->_filePutContents($r, $ab);
return $cw;
}
static function load($bw)
{
$em =& self::getInstance();
$eo = time();
if (!$em->test($bw)) {
return false;
}
$ep = $em->_file(self::CLEAR_ALL_KEY);
$r = $em->_file($bw);
if (is_file($ep) && filemtime($ep) > filemtime($r)) {
return false;
}
$ab = $em->_fileGetContents($r);
if (empty($ab[self::FILE_LIFE_KEY]) || $eo < $ab[self::FILE_LIFE_KEY]) {
unset($ab[self::FILE_LIFE_KEY]);
return $ab;
}
return false;
}
protected function _filePutContents($r, $eq)
{
$em =& self::getInstance();
$er = false;
$es = @fopen($r, 'ab+');
if ($es) {
if ($em->_options['file_locking']) {
@flock($es, LOCK_EX);
}
fseek($es, 0);
ftruncate($es, 0);
$et = @fwrite($es, $eq);
if (!($et === false)) {
$er = true;
}
@fclose($es);
}
@chmod($r, $em->_options['cache_file_umask']);
return $er;
}
protected function _file($bw)
{
$em =& self::getInstance();
$eu = $em->_idToFileName($bw);
return $em->_options['cache_dir'] . $eu;
}
protected function _idToFileName($bw)
{
$em =& self::getInstance();
$em->_id = $bw;
$x = $em->_options['file_name_prefix'];
$er = $x . '---' . $bw;
return $er;
}
static function test($bw)
{
$em =& self::getInstance();
$r = $em->_file($bw);
if (!is_file($r)) {
return false;
}
return true;
}
protected function _fileGetContents($r)
{
if (!is_file($r)) {
return false;
}
return include $r;
}
static function clear()
{
$em =& self::getInstance();
$em->save('CLEAR_ALL', self::CLEAR_ALL_KEY);
}
static function del($bw)
{
$em =& self::getInstance();
if (!$em->test($bw)) {
return false;
}
$r = $em->_file($bw);
return unlink($r);
}
}
class mdb
{
private static $_client;
private static $_db;
private static $_ins;
public static function obj($cu = 'myapp_dev')
{
if (!self::$_client) {
self::$_client = new \Sokil\Mongo\Client();
}
if (!self::$_db) {
self::$_db = self::$_client->{$cu};
}
return self::$_db;
}
public static function test()
{
$bf = 1;
$ev = self::obj()->blogs;
$ew = $ev->find()->findAll();
$ab = object2array($ew);
$ex = 1;
foreach ($ab as $by => $ey) {
unset($ey['_id']);
unset($ey['tid']);
unset($ey['tags']);
if (isset($ey['_intm'])) {
$ey['_intm'] = date('Y-m-d H:i:s', $ey['_intm']['sec']);
}
if (isset($ey['_uptm'])) {
$ey['_uptm'] = date('Y-m-d H:i:s', $ey['_uptm']['sec']);
}
$ey['uid'] = $bf;
$v = db::save('blogs', $ey);
$ex++;
}
echo 'finish test';
die;
}
}
class rds
{
private static $_client;
private static $_ins;
private static $tbl_desc = array();
public static function obj()
{
if (!self::$_client) {
self::$_client = $ce = new Predis\Client(cfg::get_redis_cfg());
}
return self::$_client;
}
}
class uc
{
static $UC_HOST = 'http://uc.xxx.com/';
const API = array('user' => '/api/user', 'accessToken' => '/api/oauth/accessToken', 'userRole' => '/api/user/role', 'createDomain' => "/api/domain", 'finduser' => '/api/users', 'userAccessToken' => '/api/user/accessToken', 'userdomain' => '/api/user/domain');
static $code_user = null;
static $pwd_user = null;
static $id_user = null;
static $user_info = null;
static $user_role = null;
private static $oauth_cfg;
public static function init($ez = array())
{
$m = cfg::get('oauth', 'oauth')['uc'];
$m['host'] = env('UC_HOST', $m['host']);
$m['clientId'] = env('UC_CLIENT_ID', $m['clientId']);
$m['clientSecret'] = env('UC_CLIENT_SECRET', $m['clientSecret']);
$m['redirectUri'] = env('UC_REDIRECT_URI', $m['redirectUri']);
$m['username'] = env('UC_USERNAME', $m['username']);
$m['passwd'] = env('UC_PASSWD', $m['passwd']);
self::$oauth_cfg = $m;
if (isset($ez['host'])) {
self::$UC_HOST = $ez['host'];
}
}
public static function makeUrl($bt, $bc = '')
{
if (!self::$oauth_cfg) {
self::init();
}
return self::$oauth_cfg['host'] . $bt . ($bc ? '?' . $bc : '');
}
public static function pwd_login($fg = null, $fh = null, $fi = null, $fj = null)
{
$fk = $fg ? $fg : self::$oauth_cfg['username'];
$cg = $fh ? $fh : self::$oauth_cfg['passwd'];
$fl = $fi ? $fi : self::$oauth_cfg['clientId'];
$fm = $fj ? $fj : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $fl, 'client_secret' => $fm, 'grant_type' => 'password', 'username' => $fk, 'password' => $cg];
$fn = self::makeUrl(self::API['accessToken']);
$ct = curl($fn, 10, 30, $ab);
$v = json_decode($ct, true);
self::_set_pwd_user($v);
return $v;
}
public static function get_admin_token($fo = array())
{
if (isset($fo['access_token'])) {
$bj = $fo['access_token'];
} else {
$v = self::pwd_login();
$bj = $v['data']['access_token'];
}
return $bj;
}
public static function id_login($bw, $fi = null, $fj = null, $ch = array())
{
$fl = $fi ? $fi : self::$oauth_cfg['clientId'];
$fm = $fj ? $fj : self::$oauth_cfg['clientSecret'];
$bj = self::get_admin_token($ch);
$ab = ['client_id' => $fl, 'client_secret' => $fm, 'grant_type' => 'id', 'access_token' => $bj, 'id' => $bw];
$fn = self::makeUrl(self::API['userAccessToken']);
$ct = curl($fn, 10, 30, $ab);
$v = json_decode($ct, true);
self::_set_id_user($v);
return $v;
}
public static function authurl($bl, $fp, $bj)
{
$fq = self::$oauth_cfg['host'] . "/api/sso/redirect?access_token={$bj}&app_id={$bl}&domain_id={$fp}";
return $fq;
}
public static function code_login($fr, $fs = null, $fi = null, $fj = null)
{
$ft = $fs ? $fs : self::$oauth_cfg['redirectUri'];
$fl = $fi ? $fi : self::$oauth_cfg['clientId'];
$fm = $fj ? $fj : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $fl, 'client_secret' => $fm, 'grant_type' => 'authorization_code', 'redirect_uri' => $ft, 'code' => $fr];
$fn = self::makeUrl(self::API['accessToken']);
$ct = curl($fn, 10, 30, $ab);
$v = json_decode($ct, true);
self::_set_code_user($v);
return $v;
}
public static function user_info($bj)
{
$fn = self::makeUrl(self::API['user'], 'access_token=' . $bj);
$ct = curl($fn);
$v = json_decode($ct, true);
self::_set_user_info($v);
return $v;
}
public static function reg_user($fk, $fh = '123456', $ch = array())
{
$bj = self::get_admin_token($ch);
$ab = ['username' => $fk, 'password' => $fh, 'access_token' => $bj];
$fn = self::makeUrl(self::API['user']);
$ct = curl($fn, 10, 30, $ab);
$fu = json_decode($ct, true);
return $fu;
}
public static function register_user($fk, $fh = '123456')
{
return self::reg_user($fk, $fh);
}
public static function find_user($fo = array())
{
$bj = self::get_admin_token($fo);
$bc = 'access_token=' . $bj;
if (isset($fo['username'])) {
$bc .= '&username=' . $fo['username'];
}
if (isset($fo['phone'])) {
$bc .= '&phone=' . $fo['phone'];
}
$fn = self::makeUrl(self::API['finduser'], $bc);
$ct = curl($fn, 10, 30);
$fu = json_decode($ct, true);
return $fu;
}
public static function edit_user($bj, $ab = array())
{
$fn = self::makeUrl(self::API['user']);
$ab['access_token'] = $bj;
$ce = new \GuzzleHttp\Client();
$cw = $ce->request('PUT', $fn, ['form_params' => $ab, 'headers' => ['X-Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded']]);
$ct = $cw->getBody();
return json_decode($ct, true);
}
public static function set_user_role($bj, $fp, $fv, $fw = 'guest')
{
$ab = ['access_token' => $bj, 'domain_id' => $fp, 'user_id' => $fv, 'role_name' => $fw];
$fn = self::makeUrl(self::API['userRole']);
$ct = curl($fn, 10, 30, $ab);
return json_decode($ct, true);
}
public static function user_role($bj, $fp)
{
$ab = ['access_token' => $bj, 'domain_id' => $fp];
$fn = self::makeUrl(self::API['userRole']);
$fn = "{$fn}?access_token={$bj}&domain_id={$fp}";
$ct = curl($fn, 10, 30);
$v = json_decode($ct, true);
self::_set_user_role($v);
return $v;
}
public static function has_role($fx)
{
if (self::$user_role && isset(self::$user_role['roles'])) {
$fy = self::$user_role['roles'];
foreach ($fy as $k => $fw) {
if ($fw['name'] == $fx) {
return true;
}
}
}
return false;
}
public static function create_domain($fz, $gh, $ch = array())
{
$bj = self::get_admin_token($ch);
$ab = ['access_token' => $bj, 'domain_name' => $fz, 'description' => $gh];
$fn = self::makeUrl(self::API['createDomain']);
$ct = curl($fn, 10, 30, $ab);
$v = json_decode($ct, true);
return $v;
}
public static function user_domain($bj)
{
$ab = ['access_token' => $bj];
$fn = self::makeUrl(self::API['userdomain']);
$fn = "{$fn}?access_token={$bj}";
$ct = curl($fn, 10, 30);
$v = json_decode($ct, true);
return $v;
}
public static function _set_pwd_user($v)
{
if ($v['code'] == 0) {
self::$pwd_user = $v['data'];
}
}
public static function _set_code_user($v)
{
if ($v['code'] == 0) {
self::$code_user = $v['data'];
}
}
public static function _set_id_user($v)
{
if ($v['code'] == 0) {
self::$id_user = $v['data'];
}
}
public static function _set_user_info($v)
{
if ($v['code'] == 0) {
self::$user_info = $v['data'];
}
}
public static function _set_user_role($v)
{
if ($v['code'] == 0) {
self::$user_role = $v['data'];
}
}
}
class vld
{
public static function test($bv, $ab)
{
}
public static function registration($ab)
{
$bz = new Valitron\Validator($ab);
$gi = ['required' => [['name'], ['gender'], ['birthdate'], ['blood'], ['nationality'], ['country'], ['mobile'], ['emergency_contact_person'], ['emergency_mobile'], ['cloth_size']], 'length' => [['name', 4]], 'mobile' => [['mobile']]];
$bz->rules($gi);
$bz->labels(['name' => '名称', 'gender' => '性别', 'birthdate' => '生日']);
if ($bz->validate()) {
return 0;
} else {
err($bz->errors());
}
}
}
}
namespace mid {
use FastRoute\Dispatcher;
use FastRoute\RouteParser\Std as StdParser;
class TwigMid
{
public function __invoke($gj, $gk, $gl)
{
log_time("Twig Begin");
$gk = $gl($gj, $gk);
$gm = uripath($gj);
debug(">>>>>> TwigMid START : {$gm}  <<<<<<");
if ($gn = $this->getRoutePath($gj)) {
$bs = \ctx::app()->getContainer()->view;
if (\ctx::isRetJson()) {
ret($bs->data);
}
$go = rtrim($gn, '/');
if ($go == '/' || !$go) {
$go = 'index';
}
$br = $go;
$ab = [];
if (isset($bs->data)) {
$ab = $bs->data;
if (isset($bs->data['tpl'])) {
$br = $bs->data['tpl'];
}
}
$ab['uid'] = \ctx::uid();
$ab['isLogin'] = \ctx::user() ? true : false;
$ab['user'] = \ctx::user();
$ab['authuser'] = \ctx::user();
$ab['uri'] = \ctx::uri();
$ab['t'] = time();
$ab['domain'] = \cfg::get('wechat_callback_domain');
$ab['gdata'] = \ctx::global_view_data();
debug("<<<<<< TwigMid END : {$gm} >>>>>");
log_time("Twig End");
log_time("Twig Total", 'begin');
return $bs->render($gk, tpl($br), $ab);
} else {
return $gk;
}
}
public function getRoutePath($gj)
{
$gp = \ctx::router()->dispatch($gj);
if ($gp[0] === Dispatcher::FOUND) {
$aj = \ctx::router()->lookupRoute($gp[1]);
$gq = $aj->getPattern();
$gr = new StdParser();
$gs = $gr->parse($gq);
foreach ($gs as $gt) {
foreach ($gt as $ef) {
if (is_string($ef)) {
return $ef;
}
}
}
}
return '';
}
}
}
namespace mid {
class AuthMid
{
private $isAjax = true;
public function __invoke($gj, $gk, $gl)
{
log_time("AuthMid Begin");
$gm = uripath($gj);
debug(">>>>>> AuthMid START : {$gm}  <<<<<<");
\ctx::init($gj);
$this->check_auth($gj, $gk);
debug("<<<<<< AuthMid END : {$gm} >>>>>");
log_time("AuthMid END");
$gk = $gl($gj, $gk);
return $gk;
}
public function isAjax($bt = '')
{
$gu = \cfg::get('route_type');
if ($gu) {
if ($gu['default'] == 'web') {
$this->isAjax = false;
}
if (isset($gu['web'])) {
}
if (isset($gu['api'])) {
}
}
if ($bt) {
if (startWith($bt, '/rest')) {
$this->isAjax = true;
}
}
return $this->isAjax;
}
public function check_auth($z, $bp)
{
list($gv, $ak, $gw) = $this->auth_cfg();
$gm = uripath($z);
$this->isAjax($gm);
if ($gm == '/') {
return true;
}
$gx = $this->check_list($gv, $gm);
if ($gx) {
$this->check_admin();
}
$gy = $this->check_list($ak, $gm);
if ($gy) {
$this->check_user();
}
$gz = $this->check_list($gw, $gm);
if (!$gz) {
$this->check_user();
}
info("check_auth: {$gm} admin:[{$gx}] user:[{$gy}] pub:[{$gz}]");
}
public function check_admin()
{
if (\ctx::isAdmin()) {
return true;
}
$this->auth_error(2);
}
public function check_user()
{
if (!\ctx::user()) {
$this->auth_error();
}
}
public function auth_error($hi = 1)
{
$hj = is_weixin();
$hk = isMobile();
$hl = \cfg::get('wechat_callback_domain');
info("auth_error: errorid: {$hi}, is_weixin: {$hj} , is_mobile: {$hk}");
$hm = $_SERVER['REQUEST_URI'];
if ($hj) {
header("Location: {$hl}/auth/wechat?_r={$hm}");
exit;
}
if ($hk) {
header("Location: {$hl}/auth/openwechat?_r={$hm}");
exit;
}
if ($this->isAjax()) {
ret($hi, 'auth error');
} else {
header('Location: /?_r=' . $hm);
exit;
}
}
public function auth_cfg()
{
$hn = \cfg::get('auth');
return [$hn['admin'], $hn['user'], $hn['public']];
}
public function check_list($ai, $gm)
{
foreach ($ai as $bt) {
if (startWith($gm, $bt)) {
return true;
}
}
return false;
}
}
}
namespace mid {
class BaseMid
{
use \core\PropGeneratorTrait;
private $name = 'Base';
private $classname = '';
private $path_info = '';
public function __invoke($gj, $gk, $gl)
{
$this->init($gj, $gk, $gl);
log_time("{$this->classname} Begin");
$this->path_info = uripath($gj);
debug(">>>>>> {$this->name}Mid START : {$this->path_info}  <<<<<<");
$this->handelReq($gj, $gk);
debug("<<<<<< {$this->name}Mid END : {$this->path_info} >>>>>");
log_time("{$this->classname} End");
$gk = $gl($gj, $gk);
return $gk;
}
public function handelReq($z, $bp)
{
$bt = \cfg::get($this->classname, 'mid.yml');
if (is_array($bt)) {
$this->handlePathArray($bt, $z, $bp);
} else {
if (startWith($this->path_info, $bt)) {
$this->handlePath($z, $bp);
}
}
}
public function handlePathArray($ho, $z, $bp)
{
foreach ($ho as $bt => $hp) {
if (startWith($this->path_info, $bt)) {
debug("{$this->path_info} match {$bt} {$hp}");
$this->{$hp}($z, $bp);
break;
}
}
}
public function handlePath($z, $bp)
{
debug("handle Path {$this->path_info} .....");
}
}
}
namespace mid {
use db\Rest as rest;
use db\RestDoc as rd;
use db\Tagx as tag;
class RestMid
{
private $path_info;
private $rest_prefix;
public function __invoke($gj, $gk, $gl)
{
log_time("RestMid Begin");
$this->path_info = uripath($gj);
$this->rest_prefix = \cfg::get_rest_prefix();
debug(">>>>>> RestMid START : {$this->path_info}  <<<<<<");
if ($this->isRest($gj)) {
info("====== RestMid Handle REST: {$this->path_info}  =======");
if ($this->isApiDoc($gj)) {
$this->apiDoc($gj);
} else {
$this->handelRest($gj);
}
} else {
debug("====== RestMid PASS REST: {$this->path_info}  =======");
}
debug("<<<<<< RestMid END : {$this->path_info}  >>>>>");
log_time("RestMid Begin");
$gk = $gl($gj, $gk);
return $gk;
}
public function isApiDoc($z)
{
return startWith($this->path_info, $this->rest_prefix . '/apidoc');
}
public function isRest($z)
{
return startWith($this->path_info, $this->rest_prefix);
}
public function handelRest($z)
{
$bt = str_replace($this->rest_prefix, '', $this->path_info);
$bu = explode('/', $bt);
$bv = getArg($bu, 1, '');
$bw = getArg($bu, 2, '');
$hp = $z->getMethod();
info(" method: {$hp}, name: {$bv}, id: {$bw}");
$hq = "handle{$hp}";
$this->{$hq}($z, $bv, $bw);
}
public function handleGET($z, $bv, $bw)
{
if ($bw) {
rest::renderItem($bv, $bw);
} else {
rest::renderList($bv);
}
}
public function handlePOST($z, $bv, $bw)
{
self::beforeData($bv, 'post');
rest::renderPostData($bv);
}
public function handlePUT($z, $bv, $bw)
{
self::beforeData($bv, 'put');
rest::renderPutData($bv, $bw);
}
public function handleDELETE($z, $bv, $bw)
{
rest::delete($z, $bv, $bw);
}
public function handleOPTIONS($z, $bv, $bw)
{
sendJson([]);
}
public function beforeData($bv, $c)
{
$hr = \cfg::get('rest_maps', 'rest.yml');
if (isset($hr[$bv])) {
$m = $hr[$bv][$c];
if ($m) {
$hs = $m['xmap'];
if ($hs) {
$ab = \ctx::data();
foreach ($hs as $by => $bz) {
unset($ab[$bz]);
}
\ctx::data($ab);
}
}
}
}
public function apiDoc($z)
{
$ht = rd::genApi();
echo $ht;
die;
}
}
}
namespace db {
use db;
use db\RestHelper;
class Rest
{
private static $tbl_desc = array();
public static function whereStr($dn, $bv)
{
$v = '';
foreach ($dn as $by => $bz) {
$gq = '/(.*)\\{(.*)\\}/i';
$bx = preg_match($gq, $by, $hu);
$hv = '=';
if ($hu) {
$hw = $hu[1];
$hv = $hu[2];
} else {
$hw = $by;
}
if ($hx = db::valid_table_col($bv, $hw)) {
if ($hx == 2) {
if ($hv == 'in') {
if (is_array($bz)) {
$bz = implode("','", $bz);
}
$v .= " and t1.{$hw} {$hv} ('{$bz}')";
} else {
$v .= " and t1.{$hw}{$hv}'{$bz}'";
}
} else {
if ($hv == 'in') {
if (is_array($bz)) {
$bz = implode(',', $bz);
}
$v .= " and t1.{$hw} {$hv} ({$bz})";
} else {
$v .= " and t1.{$hw}{$hv}{$bz}";
}
}
} else {
}
info("[{$bv}] [{$hw}] [{$hx}] {$v}");
}
return $v;
}
public static function getSqlFrom($bv, $hy, $bf, $hz, $ij, $ch = array())
{
$ik = isset($_GET['tags']) ? 1 : isset($ch['tags']) ? 1 : 0;
$il = isset($_GET['isar']) ? 1 : 0;
$im = RestHelper::get_rest_xwh_tags_list();
if ($im && in_array($bv, $im)) {
$ik = 0;
}
$in = isset($ch['force_ar']) || RestHelper::isAdmin() && $il ? "1=1" : "t1.uid={$bf}";
if ($ik) {
$io = isset($_GET['tags']) ? get('tags') : $ch['tags'];
if ($io && is_array($io) && count($io) == 1 && !$io[0]) {
$io = '';
}
$ip = '';
$iq = 'not in';
if ($io) {
if (is_string($io)) {
$io = [$io];
}
$ir = implode("','", $io);
$ip = "and `name` in ('{$ir}')";
$iq = 'in';
$is = " from {$bv} t1\n                               join tag_items t on t1.id=t.`oid`\n                               {$hy}\n                               where {$in} and t._st=1  and t.tagid {$iq}\n                               (select id from tags where type='{$bv}' {$ip} )\n                               {$ij}";
} else {
$is = " from {$bv} t1\n                              {$hy}\n                              where {$in} and t1.id not in\n                              (select oid from tag_items where type='{$bv}')\n                              {$ij}";
}
} else {
$it = $in;
if (isset($ch['force_ar']) || RestHelper::isAdmin() && $il) {
} else {
if ($bv == RestHelper::user_tbl()) {
$it = "t1.id={$bf}";
}
}
$is = "from {$bv} t1 {$hy} where {$it} {$hz} {$ij}";
}
return $is;
}
public static function getSql($bv, $ch = array())
{
$bf = RestHelper::uid();
$iu = RestHelper::get('sort', '_intm');
$iv = RestHelper::get('asc', -1);
if (!db::valid_table_col($bv, $iu)) {
$iu = '_intm';
}
$iv = $iv > 0 ? 'asc' : 'desc';
$ij = " order by t1.{$iu} {$iv}";
$iw = RestHelper::gets();
$iw = un_select_keys(['sort', 'asc'], $iw);
$ix = RestHelper::get('_st', 1);
$dn = dissoc($iw, ['token', '_st']);
if ($ix != 'all') {
$dn['_st'] = $ix;
}
$hz = self::whereStr($dn, $bv);
$iy = RestHelper::get('search', '');
$iz = RestHelper::get('search-key', '');
if ($iy && $iz) {
$hz .= " and {$iz} like '%{$iy}%'";
}
$jk = RestHelper::select_add();
$hy = RestHelper::join_add();
$jl = RestHelper::get('fields', []);
$jm = 't1.*';
if ($jl) {
$jm = '';
foreach ($jl as $jn) {
if ($jm) {
$jm .= ',';
}
$jm .= 't1.' . $jn;
}
}
$is = self::getSqlFrom($bv, $hy, $bf, $hz, $ij, $ch);
$de = "select {$jm} {$jk} {$is}";
$jo = "select count(*) cnt {$is}";
$ag = RestHelper::offset();
$af = RestHelper::pagesize();
$de .= " limit {$ag},{$af}";
return [$de, $jo];
}
public static function getResName($bv, $ch)
{
$jp = getArg($ch, 'res_name', '');
if ($jp) {
return $jp;
}
$jq = RestHelper::get('res_id_key', '');
if ($jq) {
$jr = RestHelper::get($jq);
$bv .= '_' . $jr;
}
return $bv;
}
public static function getList($bv, $ch = array())
{
$bf = RestHelper::uid();
list($de, $jo) = self::getSql($bv, $ch);
info($de);
$dk = db::query($de);
$aq = (int) db::queryOne($jo);
$js = RestHelper::get_rest_join_tags_list();
if ($js && in_array($bv, $js)) {
$jt = getKeyValues($dk, 'id');
$io = RestHelper::get_tags_by_oid($bf, $jt, $bv);
info("get tags ok: {$bf} {$bv} " . json_encode($jt));
foreach ($dk as $by => $dv) {
if (isset($io[$dv['id']])) {
$ju = $io[$dv['id']];
$dk[$by]['tags'] = getKeyValues($ju, 'name');
}
}
info('set tags ok');
}
if (isset($ch['join_cols'])) {
foreach ($ch['join_cols'] as $jv => $jw) {
$jx = getArg($jw, 'jtype', '1-1');
$jy = getArg($jw, 'jkeys', []);
$jz = getArg($jw, 'jwhe', []);
$kl = getArg($jw, 'ast', ['id' => 'ASC']);
if (is_string($jw['on'])) {
$km = 'id';
$kn = $jw['on'];
} else {
if (is_array($jw['on'])) {
$ko = array_keys($jw['on']);
$km = $ko[0];
$kn = $jw['on'][$km];
}
}
$jt = getKeyValues($dk, $km);
$jz[$kn] = $jt;
$kp = \db::all($jv, ['AND' => $jz, 'ORDER' => $kl]);
foreach ($kp as $k => $kq) {
foreach ($dk as $by => &$dv) {
if (isset($dv[$km]) && isset($kq[$kn]) && $dv[$km] == $kq[$kn]) {
if ($jx == '1-1') {
foreach ($jy as $kr => $ks) {
$dv[$ks] = $kq[$kr];
}
}
$kr = isset($jw['jkey']) ? $jw['jkey'] : $jv;
if ($jx == '1-n') {
$dv[$kr][] = $kq[$kr];
}
if ($jx == '1-n-o') {
$dv[$kr][] = $kq;
}
if ($jx == '1-1-o') {
$dv[$kr] = $kq;
}
}
}
}
}
}
$jp = self::getResName($bv, $ch);
\ctx::count($aq);
$kt = ['pageinfo' => \ctx::pageinfo()];
return ['data' => $dk, 'res-name' => $jp, 'count' => $aq, 'meta' => $kt];
}
public static function renderList($bv)
{
ret(self::getList($bv));
}
public static function getItem($bv, $bw)
{
$bf = RestHelper::uid();
info("---GET---: {$bv}/{$bw}");
$jp = "{$bv}-{$bw}";
if ($bv == 'colls') {
$ef = db::row($bv, ["{$bv}.id" => $bw], ["{$bv}.id", "{$bv}.title", "{$bv}.from_url", "{$bv}._intm", "{$bv}._uptm", "posts.content"], ['[>]posts' => ['uuid' => 'uuid']]);
} else {
if ($bv == 'feeds') {
$c = RestHelper::get('type');
$ku = RestHelper::get('rid');
$ef = db::row($bv, ['AND' => ['uid' => $bf, 'rid' => $bw, 'type' => $c]]);
if (!$ef) {
$ef = ['rid' => $bw, 'type' => $c, 'excerpt' => '', 'title' => ''];
}
$jp = "{$jp}-{$c}-{$bw}";
} else {
$ef = db::row($bv, ['id' => $bw]);
}
}
if ($kv = RestHelper::rest_extra_data()) {
$ef = array_merge($ef, $kv);
}
return ['data' => $ef, 'res-name' => $jp, 'count' => 1];
}
public static function renderItem($bv, $bw)
{
ret(self::getItem($bv, $bw));
}
public static function postData($bv)
{
$ab = db::tbl_data($bv, RestHelper::data());
$bf = RestHelper::uid();
$io = [];
if ($bv == 'tags') {
$io = RestHelper::get_tag_by_name($bf, $ab['name'], $ab['type']);
}
if ($io && $bv == 'tags') {
$ab = $io[0];
} else {
info("---POST---: {$bv} " . json_encode($ab));
unset($ab['token']);
$ab['_intm'] = date('Y-m-d H:i:s');
if (!isset($ab['uid'])) {
$ab['uid'] = $bf;
}
$ab = db::tbl_data($bv, $ab);
$ab = db::save($bv, $ab);
}
return $ab;
}
public static function renderPostData($bv)
{
$ab = self::postData($bv);
ret($ab);
}
public static function putData($bv, $bw)
{
if ($bw == 0 || $bw == '' || trim($bw) == '') {
info(" PUT ID IS EMPTY !!!");
ret();
}
$bf = RestHelper::uid();
$ab = RestHelper::data();
unset($ab['token']);
unset($ab['uniqid']);
self::checkOwner($bv, $bw, $bf);
if (isset($ab['inc'])) {
$jn = $ab['inc'];
unset($ab['inc']);
db::exec("UPDATE {$bv} SET {$jn} = {$jn} + 1 WHERE id={$bw}");
}
if (isset($ab['dec'])) {
$jn = $ab['dec'];
unset($ab['dec']);
db::exec("UPDATE {$bv} SET {$jn} = {$jn} - 1 WHERE id={$bw}");
}
if (isset($ab['tags'])) {
RestHelper::del_tag_by_name($bf, $bw, $bv);
$io = $ab['tags'];
foreach ($io as $kw) {
$kx = RestHelper::get_tag_by_name($bf, $kw, $bv);
if ($kx) {
$ky = $kx[0]['id'];
RestHelper::save_tag_items($bf, $ky, $bw, $bv);
}
}
}
info("---PUT---: {$bv}/{$bw} " . json_encode($ab));
$ab = db::tbl_data($bv, $ab);
$ab['id'] = $bw;
db::save($bv, $ab);
return $ab;
}
public static function renderPutData($bv, $bw)
{
$ab = self::putData($bv, $bw);
ret($ab);
}
public static function delete($z, $bv, $bw)
{
$bf = RestHelper::uid();
self::checkOwner($bv, $bw, $bf);
db::save($bv, ['_st' => 0, 'id' => $bw]);
ret([]);
}
public static function checkOwner($bv, $bw, $bf)
{
$dn = ['AND' => ['id' => $bw], 'LIMIT' => 1];
$dk = db::obj()->select($bv, '*', $dn);
if ($dk) {
$ef = $dk[0];
} else {
$ef = null;
}
if ($ef) {
if (array_key_exists('uid', $ef)) {
$kz = $ef['uid'];
if ($bv == RestHelper::user_tbl()) {
$kz = $ef['id'];
}
if ($kz != $bf && (!RestHelper::isAdmin() || !RestHelper::isAdminRest())) {
ret(311, 'owner error');
}
} else {
if (!RestHelper::isAdmin()) {
ret(311, 'owner error');
}
}
} else {
ret(312, 'not found error');
}
}
}
}
namespace db {
class RestHelper
{
private static $_ins = null;
public static function ins($lm = null)
{
if ($lm) {
self::$_ins = $lm;
}
if (!self::$_ins && class_exists('\\db\\RestHelperIns')) {
self::$_ins = new \db\RestHelperIns();
}
return self::$_ins;
}
public static function get_rest_xwh_tags_list()
{
return self::ins()->get_rest_xwh_tags_list();
}
public static function get_rest_join_tags_list()
{
return self::ins()->get_rest_join_tags_list();
}
public static function rest_extra_data()
{
return self::ins()->rest_extra_data();
}
public static function get_tags_by_oid($bf, $jt, $bv)
{
return self::ins()->get_tags_by_oid($bf, $jt, $bv);
}
public static function get_tag_by_name($bf, $bv, $c)
{
return self::ins()->get_tag_by_name($bf, $bv, $c);
}
public static function del_tag_by_name($bf, $bw, $bv)
{
return self::ins()->del_tag_by_name($bf, $bw, $bv);
}
public static function save_tag_items($bf, $ky, $bw, $bv)
{
return self::ins()->save_tag_items($bf, $ky, $bw, $bv);
}
public static function isAdmin()
{
return self::ins()->isAdmin();
}
public static function isAdminRest()
{
return self::ins()->isAdminRest();
}
public static function user_tbl()
{
return self::ins()->user_tbl();
}
public static function data()
{
return self::ins()->data();
}
public static function uid()
{
return self::ins()->uid();
}
public static function get($by, $ln = '')
{
return self::ins()->get($by, $ln);
}
public static function gets()
{
return self::ins()->gets();
}
public static function select_add()
{
return self::ins()->select_add();
}
public static function join_add()
{
return self::ins()->join_add();
}
public static function offset()
{
return self::ins()->offset();
}
public static function pagesize()
{
return self::ins()->pagesize();
}
}
}
namespace db {
interface RestHelperIF
{
public function get_rest_xwh_tags_list();
public function get_rest_join_tags_list();
public function rest_extra_data();
public function get_tags_by_oid($bf, $jt, $bv);
public function get_tag_by_name($bf, $bv, $c);
public function del_tag_by_name($bf, $bw, $bv);
public function save_tag_items($bf, $ky, $bw, $bv);
public function isAdmin();
public function isAdminRest();
public function user_tbl();
public function data();
public function uid();
public function get($by, $ln);
public function gets();
public function select_add();
public function join_add();
public function offset();
public function pagesize();
}
}
namespace db {
use db\Tagx as tag;
class RestHelperIns implements RestHelperIF
{
public function get_rest_xwh_tags_list()
{
return ['afwefwe'];
}
public function get_rest_join_tags_list()
{
return \cfg::rest('rest_join_tags_list');
}
public function rest_extra_data()
{
return \ctx::rest_extra_data();
}
public function get_tags_by_oid($bf, $jt, $bv)
{
return tag::getTagsByOids($bf, $jt, $bv);
}
public function get_tag_by_name($bf, $bv, $c)
{
return tag::getTagByName($bf, $bv, $c);
}
public function del_tag_by_name($bf, $bw, $bv)
{
return tag::delTagByOid($bf, $bw, $bv);
}
public function save_tag_items($bf, $ky, $bw, $bv)
{
return tag::saveTagItems($bf, $ky, $bw, $bv);
}
public function isAdmin()
{
return \ctx::isAdmin();
}
public function isAdminRest()
{
return \ctx::isAdminRest();
}
public function user_tbl()
{
return \ctx::user_tbl();
}
public function data()
{
return \ctx::data();
}
public function uid()
{
return \ctx::uid();
}
public function get($by, $ln)
{
return get($by, $ln);
}
public function gets()
{
return gets();
}
public function select_add()
{
return \ctx::rest_select_add();
}
public function join_add()
{
return \ctx::rest_join_add();
}
public function offset()
{
return \ctx::offset();
}
public function pagesize()
{
return \ctx::pagesize();
}
}
}
namespace db {
class Tagx
{
public static $tbl_name = 'tags';
public static $tbl_items_name = 'tag_items';
public static function getTagByName($bf, $kw, $c)
{
$io = \db::all(self::$tbl_name, ['AND' => ['uid' => $bf, 'name' => $kw, 'type' => $c, '_st' => 1]]);
return $io;
}
public static function delTagByOid($bf, $lo, $lp)
{
info("del tag: {$bf}, {$lo}, {$lp}");
$v = \db::update(self::$tbl_items_name, ['_st' => 0], ['AND' => ['uid' => $bf, 'oid' => $lo, 'type' => $lp]]);
info($v);
}
public static function saveTagItems($bf, $lq, $lo, $lp)
{
\db::save('tag_items', ['tagid' => $lq, 'uid' => $bf, 'oid' => $lo, 'type' => $lp, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')]);
}
public static function getTagsByType($bf, $c)
{
$io = \db::all(self::$tbl_name, ['AND' => ['uid' => $bf, 'type' => $c, '_st' => 1]]);
return $io;
}
public static function getTagsByOid($bf, $lo, $c)
{
$de = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid={$lo} and t2.type='{$c}' and t2._st=1";
$dk = \db::query($de);
return getKeyValues($dk, 'name');
}
public static function getTagsByOids($bf, $lr, $c)
{
if (is_array($lr)) {
$lr = implode(',', $lr);
}
$de = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid in ({$lr}) and t2.type='{$c}' and t2._st=1";
$dk = \db::query($de);
$ab = groupArray($dk, 'oid');
return $ab;
}
public static function countByTag($bf, $kw, $c)
{
$de = "select count(*) cnt, t1.id id from tags t1 join tag_items t2 on t1.id = t2.tagid where t1.name='{$kw}' and t1.type='{$c}' and t1.uid={$bf}";
$dk = \db::query($de);
return [$dk[0]['cnt'], $dk[0]['id']];
}
public static function saveTag($bf, $kw, $c)
{
$ab = ['uid' => $bf, 'name' => $kw, 'type' => $c, 'count' => 1, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
$ab = \db::save('tags', $ab);
return $ab;
}
public static function countTags($bf, $ls, $bv)
{
foreach ($ls as $kw) {
list($lt, $bw) = self::countByTag($bf, $kw, $bv);
echo "{$kw} {$lt} {$bw} <br>";
\db::update('tags', ['count' => $lt], ['id' => $bw]);
}
}
public static function saveRepoTags($bf, $lu)
{
$bv = 'stars';
echo count($lu) . "<br>";
$ls = [];
foreach ($lu as $lv) {
$lw = $lv['repoId'];
$io = isset($lv['tags']) ? $lv['tags'] : [];
if ($io) {
foreach ($io as $kw) {
if (!in_array($kw, $ls)) {
$ls[] = $kw;
}
$io = self::getTagByName($bf, $kw, $bv);
if (!$io) {
$kx = self::saveTag($bf, $kw, $bv);
} else {
$kx = $io[0];
}
$lq = $kx['id'];
$lx = getStarByRepoId($bf, $lw);
if ($lx) {
$lo = $lx[0]['id'];
$ly = self::getTagsByOid($bf, $lo, $bv);
if ($kx && !in_array($kw, $ly)) {
self::saveTagItems($bf, $lq, $lo, $bv);
}
} else {
echo "-------- star for {$lw} not found <br>";
}
}
} else {
}
}
self::countTags($bf, $ls, $bv);
}
public static function getTagItem($lz, $bf, $mn, $dy, $mo)
{
$de = "select * from {$mn} where {$dy}={$mo} and uid={$bf}";
return $lz->query($de)->fetchAll();
}
public static function saveItemTags($lz, $bf, $bv, $mp, $dy = 'id')
{
echo count($mp) . "<br>";
$ls = [];
foreach ($mp as $mq) {
$mo = $mq[$dy];
$io = isset($mq['tags']) ? $mq['tags'] : [];
if ($io) {
foreach ($io as $kw) {
if (!in_array($kw, $ls)) {
$ls[] = $kw;
}
$io = getTagByName($lz, $bf, $kw, $bv);
if (!$io) {
$kx = saveTag($lz, $bf, $kw, $bv);
} else {
$kx = $io[0];
}
$lq = $kx['id'];
$lx = getTagItem($lz, $bf, $bv, $dy, $mo);
if ($lx) {
$lo = $lx[0]['id'];
$ly = getTagsByOid($lz, $bf, $lo, $bv);
if ($kx && !in_array($kw, $ly)) {
saveTagItems($lz, $bf, $lq, $lo, $bv);
}
} else {
echo "-------- star for {$mo} not found <br>";
}
}
} else {
}
}
countTags($lz, $bf, $ls, $bv);
}
}
}
namespace core {
class Auth
{
public static function login($app, $cf = 'login', $cg = 'passwd')
{
$be = \cfg::get('user_tbl_name');
$bh = \cfg::get('use_ucenter_oauth');
$ax = cguid();
$mr = null;
$az = null;
$ab = \ctx::data();
$bi = getArg($ab, 'auth_type');
debug("auth type: {$bi}");
if ($bi) {
if ($bi == 'cube') {
info("cube auth ...");
$ax .= '$$cube';
$ak = cube_user_login($app, $cf, $cg);
if ($ak) {
$ak['luser'] = local_user('cube_uid', $ak['user']['id'], $be);
cache_user($ax, $ak);
$az = $ak['user'];
}
}
} else {
if ($bh) {
list($bj, $az, $ms) = uc_user_login($app, $cf, $cg);
$ak = $az;
$mr = ['access_token' => $bj, 'userinfo' => $az, 'role_list' => $ms, 'luser' => local_user('uc_id', $az['user_id'], $be)];
extract(cache_user($ax, $mr));
$az = select_keys(['username', 'phone', 'roles', 'email'], $az);
} else {
$ak = user_login($app, $cf, $cg, $be, 1);
if ($ak) {
$ak['username'] = $ak[$cf];
$mr = ['user' => $ak];
extract(cache_user($ax, $mr));
$dx = \cfg::get('login_userinfo_cols');
if (!$dx) {
$dx = [$cf];
}
$az = select_keys($dx, $ak);
}
}
}
if ($ak) {
ret(['token' => $ax, 'userinfo' => $az]);
} else {
ret(1, 'login error');
}
}
}
}
namespace core {
use Countable;
use ArrayAccess;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
class Dot implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
protected $items = array();
public function __construct($mt = array())
{
$this->items = $this->getArrayItems($mt);
}
public function add($dx, $l = null)
{
if (is_array($dx)) {
foreach ($dx as $k => $l) {
$this->add($k, $l);
}
} elseif (is_null($this->get($dx))) {
$this->set($dx, $l);
}
}
public function all()
{
return $this->items;
}
public function clear($dx = null)
{
if (is_null($dx)) {
$this->items = [];
return;
}
$dx = (array) $dx;
foreach ($dx as $k) {
$this->set($k, []);
}
}
public function delete($dx)
{
$dx = (array) $dx;
foreach ($dx as $k) {
if ($this->exists($this->items, $k)) {
unset($this->items[$k]);
continue;
}
$mt =& $this->items;
$mu = explode('.', $k);
$mv = array_pop($mu);
foreach ($mu as $mw) {
if (!isset($mt[$mw]) || !is_array($mt[$mw])) {
continue 2;
}
$mt =& $mt[$mw];
}
unset($mt[$mv]);
}
}
protected function exists($mx, $k)
{
return array_key_exists($k, $mx);
}
public function get($k = null, $my = null)
{
if (is_null($k)) {
return $this->items;
}
if ($this->exists($this->items, $k)) {
return $this->items[$k];
}
if (strpos($k, '.') === false) {
return $my;
}
$mt = $this->items;
foreach (explode('.', $k) as $mw) {
if (!is_array($mt) || !$this->exists($mt, $mw)) {
return $my;
}
$mt =& $mt[$mw];
}
return $mt;
}
protected function getArrayItems($mt)
{
if (is_array($mt)) {
return $mt;
} elseif ($mt instanceof self) {
return $mt->all();
}
return (array) $mt;
}
public function has($dx)
{
$dx = (array) $dx;
if (!$this->items || $dx === []) {
return false;
}
foreach ($dx as $k) {
$mt = $this->items;
if ($this->exists($mt, $k)) {
continue;
}
foreach (explode('.', $k) as $mw) {
if (!is_array($mt) || !$this->exists($mt, $mw)) {
return false;
}
$mt = $mt[$mw];
}
}
return true;
}
public function isEmpty($dx = null)
{
if (is_null($dx)) {
return empty($this->items);
}
$dx = (array) $dx;
foreach ($dx as $k) {
if (!empty($this->get($k))) {
return false;
}
}
return true;
}
public function merge($k, $l = null)
{
if (is_array($k)) {
$this->items = array_merge($this->items, $k);
} elseif (is_string($k)) {
$mt = (array) $this->get($k);
$l = array_merge($mt, $this->getArrayItems($l));
$this->set($k, $l);
} elseif ($k instanceof self) {
$this->items = array_merge($this->items, $k->all());
}
}
public function pull($k = null, $my = null)
{
if (is_null($k)) {
$l = $this->all();
$this->clear();
return $l;
}
$l = $this->get($k, $my);
$this->delete($k);
return $l;
}
public function push($k, $l = null)
{
if (is_null($l)) {
$this->items[] = $k;
return;
}
$mt = $this->get($k);
if (is_array($mt) || is_null($mt)) {
$mt[] = $l;
$this->set($k, $mt);
}
}
public function set($dx, $l = null)
{
if (is_array($dx)) {
foreach ($dx as $k => $l) {
$this->set($k, $l);
}
return;
}
$mt =& $this->items;
foreach (explode('.', $dx) as $k) {
if (!isset($mt[$k]) || !is_array($mt[$k])) {
$mt[$k] = [];
}
$mt =& $mt[$k];
}
$mt = $l;
}
public function setArray($mt)
{
$this->items = $this->getArrayItems($mt);
}
public function setReference(array &$mt)
{
$this->items =& $mt;
}
public function toJson($k = null, $el = 0)
{
if (is_string($k)) {
return json_encode($this->get($k), $el);
}
$el = $k === null ? 0 : $k;
return json_encode($this->items, $el);
}
public function offsetExists($k)
{
return $this->has($k);
}
public function offsetGet($k)
{
return $this->get($k);
}
public function offsetSet($k, $l)
{
if (is_null($k)) {
$this->items[] = $l;
return;
}
$this->set($k, $l);
}
public function offsetUnset($k)
{
$this->delete($k);
}
public function count($k = null)
{
return count($this->get($k));
}
public function getIterator()
{
return new ArrayIterator($this->items);
}
public function jsonSerialize()
{
return $this->items;
}
}
}
namespace core {
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
class Service
{
public $service = '';
public $prefix = '';
public $client;
public $resp;
private static $_services = array();
private static $_ins = array();
public function __construct($mz = '')
{
if ($mz) {
$this->service = $mz;
$ch = self::$_services[$this->service];
$no = $ch['url'];
debug("init client: {$no}");
$this->client = new Client(['base_uri' => $no, 'timeout' => 12.0]);
}
}
public static function add($ch = array())
{
if ($ch) {
$bv = $ch['name'];
if (!isset(self::$_services[$bv])) {
self::$_services[$bv] = $ch;
}
}
}
public static function init()
{
$np = \cfg::get('service_list', 'service');
if ($np) {
foreach ($np as $m) {
self::add($m);
}
}
}
public function getRest($mz, $x = '/rest')
{
return $this->getService($mz, $x . '/');
}
public function getService($mz, $x = '')
{
if (isset(self::$_services[$mz])) {
if (!isset(self::$_ins[$mz])) {
self::$_ins[$mz] = new Service($mz);
}
}
if (isset(self::$_ins[$mz])) {
$lm = self::$_ins[$mz];
if ($x) {
$lm->setPrefix($x);
}
return $lm;
} else {
return null;
}
}
public function setPrefix($x)
{
$this->prefix = $x;
}
public function json()
{
$bx = $this->body();
$ab = json_decode($bx, true);
return $ab;
}
public function body()
{
if ($this->resp) {
return $this->resp->getBody();
}
}
public function __call($hp, $nq)
{
$ch = self::$_services[$this->service];
$no = $ch['url'];
$bl = $ch['appid'];
$bg = $ch['appkey'];
$nr = getArg($nq, 0, []);
$ab = getArg($nr, 'data', []);
$ab = array_merge($ab, $_GET);
unset($ns['token']);
$ab['appid'] = $bl;
$ab['date'] = date("Y-m-d H:i:s");
$ab['sign'] = gen_sign($ab, $bg);
$nt = getArg($nr, 'path', '');
$nu = getArg($nr, 'suffix', '');
$nt = $this->prefix . $nt . $nu;
$hp = strtoupper($hp);
debug("api_url: {$bl} {$bg} {$no}");
debug("api_name: {$nt} [{$hp}]");
debug("data: " . json_encode($ab));
try {
if (in_array($hp, ['GET'])) {
$nv = $nw == 'GET' ? 'query' : 'form_params';
$this->resp = $this->client->request($hp, $nt, [$nv => $ab]);
}
} catch (Exception $e) {
}
return $this;
}
}
}
namespace core {
trait PropGeneratorTrait
{
public function __get($nx)
{
$hp = 'get' . ucfirst($nx);
if (method_exists($this, $hp)) {
$ny = new ReflectionMethod($this, $hp);
if (!$ny->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $nx)) {
return $this->{$nx};
}
}
public function __set($nx, $l)
{
$hp = 'set' . ucfirst($nx);
if (method_exists($this, $hp)) {
$ny = new ReflectionMethod($this, $hp);
if (!$ny->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $nx)) {
$this->{$nx} = $l;
}
}
}
}
namespace core {
class TreeBuilder
{
protected $leafIndex = array();
protected $tree = array();
protected $stack;
protected $pid_key = 'pid';
protected $name_key = 'name';
protected $children_key = 'children';
protected $ext_keys = array();
protected $pick_node_id = 0;
function __construct($ab, $ch = array())
{
$this->stack = $ab;
if (isset($ch['pid_key'])) {
$this->pid_key = $ch['pid_key'];
}
if (isset($ch['name_key'])) {
$this->name_key = $ch['name_key'];
}
if (isset($ch['children_key'])) {
$this->children_key = $ch['children_key'];
}
if (isset($ch['ext_keys'])) {
$this->ext_keys = $ch['ext_keys'];
}
if (isset($ch['pnid'])) {
$this->pick_node_id = $ch['pnid'];
}
$nz = 100;
while (count($this->stack) && $nz > 0) {
$nz -= 1;
debug("count stack: " . count($this->stack));
$this->branchify(array_shift($this->stack));
}
}
protected function branchify(&$op)
{
if ($this->pick_node_id) {
if ($op['id'] == $this->pick_node_id) {
$this->addLeaf($this->tree, $op);
return;
}
} else {
if (null === $op[$this->pid_key] || 0 == $op[$this->pid_key]) {
$this->addLeaf($this->tree, $op);
return;
}
}
if (isset($this->leafIndex[$op[$this->pid_key]])) {
$this->addLeaf($this->leafIndex[$op[$this->pid_key]][$this->children_key], $op);
} else {
debug("back to stack: " . json_encode($op) . json_encode($this->leafIndex));
$this->stack[] = $op;
}
}
protected function addLeaf(&$oq, $op)
{
$or = array('id' => $op['id'], $this->name_key => $op['name'], 'data' => $op, $this->children_key => array());
foreach ($this->ext_keys as $by => $bz) {
if (isset($op[$by])) {
$or[$bz] = $op[$by];
}
}
$oq[] = $or;
$this->leafIndex[$op['id']] =& $oq[count($oq) - 1];
}
protected function addChild($oq, $op)
{
$this->leafIndex[$op['id']] &= $oq[$this->children_key][] = $op;
}
public function getTree()
{
return $this->tree;
}
}
}
namespace {
if (getenv('WHOOPS_ENABLED') == 'yes') {
$os = new \Whoops\Run();
$os->pushHandler(new \Whoops\Handler\PrettyPageHandler());
$os->register();
}
function getCaller($ot = NULL)
{
$ou = debug_backtrace();
$ov = $ou[2];
if (isset($ot)) {
return $ov[$ot];
} else {
return $ov;
}
}
function getCallerStr($ow = 4)
{
$ou = debug_backtrace();
$ov = $ou[2];
$ox = $ou[1];
$oy = $ov['function'];
$oz = isset($ov['class']) ? $ov['class'] : '';
$pq = $ox['file'];
$pr = $ox['line'];
if ($ow == 4) {
$bx = "{$oz} {$oy} {$pq} {$pr}";
} elseif ($ow == 3) {
$bx = "{$oz} {$oy} {$pr}";
} else {
$bx = "{$oz} {$pr}";
}
return $bx;
}
function wlog($bt, $ps, $pt)
{
if (is_dir($bt)) {
$pu = date('Y-m-d', time());
$pt .= "\n";
file_put_contents($bt . "/{$ps}-{$pu}.log", $pt, FILE_APPEND);
}
}
function folder_exist($pv)
{
$bt = realpath($pv);
return ($bt !== false and is_dir($bt)) ? $bt : false;
}
use Rosio\EncryptedCookie\CryptoSystem\AES_SHA;
function encrypt($ab, $pw)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$px = $m['symmetric_key'];
$py = $m['hmac_key'];
$pz = new AES_SHA($px, $py);
return $pz->encrypt(serialize($ab), $pw);
}
function decrypt($ab)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$px = $m['symmetric_key'];
$py = $m['hmac_key'];
$pz = new AES_SHA($px, $py);
return unserialize($pz->decrypt($ab));
}
function encrypt_cookie($qr)
{
return encrypt($qr->getData(), $qr->getExpiration());
}
function ecode($eq, $k)
{
$k = substr(openssl_digest(openssl_digest($k, 'sha1', true), 'sha1', true), 0, 16);
$ab = openssl_encrypt($eq, 'AES-128-ECB', $k, OPENSSL_RAW_DATA);
$ab = strtoupper(bin2hex($ab));
return $ab;
}
function dcode($eq, $k)
{
$k = substr(openssl_digest(openssl_digest($k, 'sha1', true), 'sha1', true), 0, 16);
$qs = openssl_decrypt(hex2bin($eq), 'AES-128-ECB', $k, OPENSSL_RAW_DATA);
return $qs;
}
define('UC_KEY', 'iHuiPaoiwoeurqoejjdfklasdjfqowiefiqwjflkjdfsdfa');
function _authcode($eq, $qt = 'DECODE', $k = '', $qu = 0)
{
$qv = 4;
$k = md5($k ? $k : UC_KEY);
$qw = md5(substr($k, 0, 16));
$qx = md5(substr($k, 16, 16));
$qy = $qv ? $qt == 'DECODE' ? substr($eq, 0, $qv) : substr(md5(microtime()), -$qv) : '';
$qz = $qw . md5($qw . $qy);
$rs = strlen($qz);
$eq = $qt == 'DECODE' ? base64_decode(substr($eq, $qv)) : sprintf('%010d', $qu ? $qu + time() : 0) . substr(md5($eq . $qx), 0, 16) . $eq;
$rt = strlen($eq);
$er = '';
$ru = range(0, 255);
$rv = array();
for ($ex = 0; $ex <= 255; $ex++) {
$rv[$ex] = ord($qz[$ex % $rs]);
}
for ($rw = $ex = 0; $ex < 256; $ex++) {
$rw = ($rw + $ru[$ex] + $rv[$ex]) % 256;
$et = $ru[$ex];
$ru[$ex] = $ru[$rw];
$ru[$rw] = $et;
}
for ($rx = $rw = $ex = 0; $ex < $rt; $ex++) {
$rx = ($rx + 1) % 256;
$rw = ($rw + $ru[$rx]) % 256;
$et = $ru[$rx];
$ru[$rx] = $ru[$rw];
$ru[$rw] = $et;
$er .= chr(ord($eq[$ex]) ^ $ru[($ru[$rx] + $ru[$rw]) % 256]);
}
if ($qt == 'DECODE') {
if ((substr($er, 0, 10) == 0 || substr($er, 0, 10) - time() > 0) && substr($er, 10, 16) == substr(md5(substr($er, 26) . $qx), 0, 16)) {
return substr($er, 26);
} else {
return '';
}
} else {
return $qy . str_replace('=', '', base64_encode($er));
}
}
function object2array(&$ry)
{
$ry = json_decode(json_encode($ry), true);
return $ry;
}
function getKeyValues($ab, $k, $df = null)
{
if (!$df) {
$df = function ($bz) {
return $bz;
};
}
$bd = array();
if ($ab && is_array($ab)) {
foreach ($ab as $ef) {
if (isset($ef[$k]) && $ef[$k]) {
$u = $ef[$k];
if ($df) {
$u = $df($u);
}
$bd[] = $u;
}
}
}
return array_unique($bd);
}
if (!function_exists('indexArray')) {
function indexArray($ab, $k, $fo = null)
{
$bd = array();
if ($ab && is_array($ab)) {
foreach ($ab as $ef) {
if (!isset($ef[$k]) || !$ef[$k] || !is_scalar($ef[$k])) {
continue;
}
if (!$fo) {
$bd[$ef[$k]] = $ef;
} else {
if (is_string($fo)) {
$bd[$ef[$k]] = $ef[$fo];
} else {
if (is_array($fo)) {
$rz = [];
foreach ($fo as $by => $bz) {
$rz[$bz] = $ef[$bz];
}
$bd[$ef[$k]] = $ef[$fo];
}
}
}
}
}
return $bd;
}
}
if (!function_exists('groupArray')) {
function groupArray($mx, $k)
{
if (!is_array($mx) || !$mx) {
return array();
}
$ab = array();
foreach ($mx as $ef) {
if (isset($ef[$k]) && $ef[$k]) {
$ab[$ef[$k]][] = $ef;
}
}
return $ab;
}
}
function select_keys($dx, $ab)
{
$v = [];
foreach ($dx as $k) {
if (isset($ab[$k])) {
$v[$k] = $ab[$k];
} else {
$v[$k] = '';
}
}
return $v;
}
function un_select_keys($dx, $ab)
{
$v = [];
foreach ($ab as $by => $ef) {
if (!in_array($by, $dx)) {
$v[$by] = $ef;
}
}
return $v;
}
function copyKey($ab, $st, $su)
{
foreach ($ab as &$ef) {
$ef[$su] = $ef[$st];
}
return $ab;
}
function addKey($ab, $k, $u)
{
foreach ($ab as &$ef) {
$ef[$k] = $u;
}
return $ab;
}
function dissoc($mx, $dx)
{
if (is_array($dx)) {
foreach ($dx as $k) {
unset($mx[$k]);
}
} else {
unset($mx[$dx]);
}
return $mx;
}
function sortIdx($ab)
{
$sv = [];
foreach ($ab as $by => $bz) {
$sv[$bz] = ['_sort' => $by + 1];
}
return $sv;
}
function insertAt($mt, $sw, $l)
{
array_splice($mt, $sw, 0, [$l]);
return $mt;
}
function getArg($nr, $sx, $my = '')
{
if (isset($nr[$sx])) {
return $nr[$sx];
} else {
return $my;
}
}
function permu($av, $dt = ',')
{
$ai = [];
if (is_string($av)) {
$sy = str_split($av);
} else {
$sy = $av;
}
sort($sy);
$sz = count($sy) - 1;
$tu = $sz;
$aq = 1;
$ef = implode($dt, $sy);
$ai[] = $ef;
while (true) {
$tv = $tu--;
if ($sy[$tu] < $sy[$tv]) {
$tw = $sz;
while ($sy[$tu] > $sy[$tw]) {
$tw--;
}
list($sy[$tu], $sy[$tw]) = array($sy[$tw], $sy[$tu]);
for ($ex = $sz; $ex > $tv; $ex--, $tv++) {
list($sy[$ex], $sy[$tv]) = array($sy[$tv], $sy[$ex]);
}
$ef = implode($dt, $sy);
$ai[] = $ef;
$tu = $sz;
$aq++;
}
if ($tu == 0) {
break;
}
}
return $ai;
}
function combin($bd, $tx, $ty = ',')
{
$er = array();
if ($tx == 1) {
return $bd;
}
if ($tx == count($bd)) {
$er[] = implode($ty, $bd);
return $er;
}
$tz = $bd[0];
unset($bd[0]);
$bd = array_values($bd);
$uv = combin($bd, $tx - 1, $ty);
foreach ($uv as $uw) {
$uw = $tz . $ty . $uw;
$er[] = $uw;
}
unset($uv);
$ux = combin($bd, $tx, $ty);
foreach ($ux as $uw) {
$er[] = $uw;
}
unset($ux);
return $er;
}
function getExcelCol($dg)
{
$bd = array(0 => 'Z', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z');
if ($dg == 0) {
return '';
}
return getExcelCol((int) (($dg - 1) / 26)) . $bd[$dg % 26];
}
function getExcelPos($dv, $dg)
{
return getExcelCol($dg) . $dv;
}
function sendJSON($ab)
{
$uy = cfg::get('aca');
if (isset($uy['origin'])) {
header("Access-Control-Allow-Origin: {$uy['origin']}");
}
$uz = "Content-Type, Authorization, Accept,X-Requested-With";
if (isset($uy['headers'])) {
$uz = $uy['headers'];
}
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: {$uz}");
header("Content-type: application/json");
header("Access-Control-Allow-Credentials:true");
log_time("sendJSON Total", 'begin');
if (is_array($ab)) {
echo json_encode($ab);
} else {
echo $ab;
}
exit;
}
function succ($bd = array(), $vw = 'succ', $vx = 1)
{
$ab = $bd;
$vy = 0;
$vz = 1;
$aq = 0;
$v = array($vw => $vx, 'errormsg' => '', 'errorfield' => '');
if (isset($bd['data'])) {
$ab = $bd['data'];
}
$v['data'] = $ab;
if (isset($bd['total_page'])) {
$v['total_page'] = $bd['total_page'];
}
if (isset($bd['cur_page'])) {
$v['cur_page'] = $bd['cur_page'];
}
if (isset($bd['count'])) {
$v['count'] = $bd['count'];
}
if (isset($bd['res-name'])) {
$v['res-name'] = $bd['res-name'];
}
if (isset($bd['meta'])) {
$v['meta'] = $bd['meta'];
}
sendJSON($v);
}
function fail($bd = array(), $vw = 'succ', $wx = 0)
{
$k = $pt = '';
if (count($bd) > 0) {
$dx = array_keys($bd);
$k = $dx[0];
$pt = $bd[$k][0];
}
$v = array($vw => $wx, 'errormsg' => $pt, 'errorfield' => $k);
sendJSON($v);
}
function code($bd = array(), $fr = 0)
{
if (is_string($fr)) {
}
if ($fr == 0) {
succ($bd, 'code', 0);
} else {
fail($bd, 'code', $fr);
}
}
function ret($bd = array(), $fr = 0, $jn = '')
{
$rx = $bd;
$wy = $fr;
if (is_numeric($bd) || is_string($bd)) {
$wy = $bd;
$rx = array();
if (is_array($fr)) {
$rx = $fr;
} else {
$fr = $fr === 0 ? '' : $fr;
$rx = array($jn => array($fr));
}
}
code($rx, $wy);
}
function response($bd = array(), $fr = 0, $jn = '')
{
ret($bd, $fr, $jn);
}
function err($wz)
{
code($wz, 1);
}
function downloadExcel($xy, $eu)
{
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition:inline;filename="' . $eu . '.xls"');
header("Content-Transfer-Encoding: binary");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
$xy->save('php://output');
}
function dd($ab)
{
if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
ret(['dd' => $ab]);
} else {
dump($ab);
exit;
}
}
function cacert_file()
{
return ROOT_PATH . "/fn/cacert.pem";
}
function curl($fn, $xz = 10, $yz = 30, $abc = '', $hp = 'post')
{
$abd = curl_init($fn);
curl_setopt($abd, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($abd, CURLOPT_CONNECTTIMEOUT, $xz);
curl_setopt($abd, CURLOPT_HEADER, 0);
curl_setopt($abd, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36');
curl_setopt($abd, CURLOPT_TIMEOUT, $yz);
if (file_exists(cacert_file())) {
curl_setopt($abd, CURLOPT_CAINFO, cacert_file());
}
if ($abc) {
if (is_array($abc)) {
$abc = http_build_query($abc);
}
if ($hp == 'post') {
curl_setopt($abd, CURLOPT_POST, 1);
} else {
if ($hp == 'put') {
curl_setopt($abd, CURLOPT_CUSTOMREQUEST, "put");
}
}
curl_setopt($abd, CURLOPT_POSTFIELDS, $abc);
}
$er = curl_exec($abd);
if (curl_errno($abd)) {
return '';
}
curl_close($abd);
return $er;
}
function curl_header($fn, $xz = 10, $yz = 30)
{
$abd = curl_init($fn);
curl_setopt($abd, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($abd, CURLOPT_CONNECTTIMEOUT, $xz);
curl_setopt($abd, CURLOPT_HEADER, 1);
curl_setopt($abd, CURLOPT_NOBODY, 1);
curl_setopt($abd, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($abd, CURLOPT_TIMEOUT, $yz);
if (file_exists(cacert_file())) {
curl_setopt($abd, CURLOPT_CAINFO, cacert_file());
}
$er = curl_exec($abd);
if (curl_errno($abd)) {
return '';
}
return $er;
}
function http($fn, $ch = array())
{
$xz = getArg($ch, 'connecttime', 10);
$yz = getArg($ch, 'timeout', 30);
$ab = getArg($ch, 'data', '');
$hp = getArg($ch, 'method', 'get');
$uz = getArg($ch, 'headers', null);
$abd = curl_init($fn);
curl_setopt($abd, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($abd, CURLOPT_CONNECTTIMEOUT, $xz);
curl_setopt($abd, CURLOPT_HEADER, 0);
curl_setopt($abd, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($abd, CURLOPT_TIMEOUT, $yz);
if (file_exists(cacert_file())) {
curl_setopt($abd, CURLOPT_CAINFO, cacert_file());
}
if ($uz) {
curl_setopt($abd, CURLOPT_HTTPHEADER, $uz);
}
if ($ab) {
curl_setopt($abd, CURLOPT_POST, 1);
if (is_array($ab)) {
$ab = http_build_query($ab);
}
curl_setopt($abd, CURLOPT_POSTFIELDS, $ab);
}
if ($hp != 'get') {
if ($hp == 'post') {
curl_setopt($abd, CURLOPT_POST, 1);
} else {
if ($hp == 'put') {
curl_setopt($abd, CURLOPT_CUSTOMREQUEST, "put");
}
}
}
$er = curl_exec($abd);
if (curl_errno($abd)) {
return '';
}
curl_close($abd);
return $er;
}
function startWith($bx, $uw)
{
return strpos($bx, $uw) === 0;
}
function endWith($abe, $abf)
{
$abg = strlen($abf);
if ($abg == 0) {
return true;
}
return substr($abe, -$abg) === $abf;
}
function is_string_column($c)
{
if (startWith(strtolower($c), 'char') || startWith(strtolower($c), 'varchar') || startWith(strtolower($c), 'datetime')) {
return 2;
} else {
return 1;
}
}
function getWhereStr($k, $ab, $abh = false, $jn = '')
{
$mx = getKeyValues($ab, $k);
if (!$mx) {
return '';
}
if ($abh) {
foreach ($mx as $by => $bz) {
$mx[$by] = "'{$bz}'";
}
}
$bx = implode(',', $mx);
if ($jn) {
$k = $jn;
}
return " {$k} in ({$bx})";
}
function get_top_domain($fn)
{
$gq = "/[\\w-]+\\.(com|net|org|gov|cc|biz|info|cn)(\\.(cn|hk))*/";
preg_match($gq, $fn, $abi);
if (count($abi) > 0) {
return $abi[0];
} else {
$abj = parse_url($fn);
$abk = $abj["host"];
if (!strcmp(long2ip(sprintf("%u", ip2long($abk))), $abk)) {
return $abk;
} else {
$bd = explode(".", $abk);
$aq = count($bd);
$abl = array("com", "net", "org", "3322");
if (in_array($bd[$aq - 2], $abl)) {
$hl = $bd[$aq - 3] . "." . $bd[$aq - 2] . "." . $bd[$aq - 1];
} else {
$hl = $bd[$aq - 2] . "." . $bd[$aq - 1];
}
return $hl;
}
}
}
function genID($ox)
{
list($abm, $abn) = explode(" ", microtime());
$abo = rand(0, 100);
return $ox . $abn . substr($abm, 2, 6);
}
function cguid($abp = false)
{
mt_srand((double) microtime() * 10000);
$abq = md5(uniqid(rand(), true));
return $abp ? strtoupper($abq) : $abq;
}
function guid()
{
if (function_exists('com_create_guid')) {
return com_create_guid();
} else {
$abr = cguid();
$abs = chr(45);
$abt = chr(123) . substr($abr, 0, 8) . $abs . substr($abr, 8, 4) . $abs . substr($abr, 12, 4) . $abs . substr($abr, 16, 4) . $abs . substr($abr, 20, 12) . chr(125);
return $abt;
}
}
function randstr($lt = 6)
{
return substr(md5(rand()), 0, $lt);
}
function hashsalt($cg, $abu = '')
{
$abu = $abu ? $abu : randstr(10);
$abv = md5(md5($cg) . $abu);
return [$abv, $abu];
}
function gen_letters($lt = 26)
{
$uw = '';
for ($ex = 65; $ex < 65 + $lt; $ex++) {
$uw .= strtolower(chr($ex));
}
return $uw;
}
function gen_sign($bc, $ax = null)
{
if ($ax == null) {
return false;
}
return strtoupper(md5(strtoupper(md5(assemble($bc))) . $ax));
}
function assemble($bc)
{
if (!is_array($bc)) {
return null;
}
ksort($bc, SORT_STRING);
$abw = '';
foreach ($bc as $k => $u) {
$abw .= $k . (is_array($u) ? assemble($u) : $u);
}
return $abw;
}
function check_sign($bc, $ax = null)
{
$abw = getArg($bc, 'sign');
$abx = getArg($bc, 'date');
$aby = strtotime($abx);
$abz = time();
$acd = $abz - $aby;
debug("check_sign : {$abz} - {$aby} = {$acd}");
if (!$abx || $abz - $aby > 60) {
debug("check_sign fail : {$abx} delta > 60");
return false;
}
unset($bc['sign']);
$ace = gen_sign($bc, $ax);
debug("{$abw} -- {$ace}");
return $abw == $ace;
}
function getIP()
{
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
$acf = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
$acf = $_SERVER["HTTP_CLIENT_IP"];
} else {
if (!empty($_SERVER["REMOTE_ADDR"])) {
$acf = $_SERVER["REMOTE_ADDR"];
} else {
if (getenv("HTTP_X_FORWARDED_FOR")) {
$acf = getenv("HTTP_X_FORWARDED_FOR");
} else {
if (getenv("HTTP_CLIENT_IP")) {
$acf = getenv("HTTP_CLIENT_IP");
} else {
if (getenv("REMOTE_ADDR")) {
$acf = getenv("REMOTE_ADDR");
} else {
$acf = "Unknown";
}
}
}
}
}
}
return $acf;
}
function getRIP()
{
$acf = $_SERVER["REMOTE_ADDR"];
return $acf;
}
function env($k = 'DEV_MODE', $my = '')
{
$l = getenv($k);
return $l ? $l : $my;
}
function vpath()
{
$bt = getenv("VENDER_PATH");
if ($bt) {
return $bt;
} else {
return ROOT_PATH;
}
}
function config($k = '')
{
return cfg::config($k);
}
function is_docker_env()
{
return env() == 'docker';
}
function is_weixin()
{
if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
return true;
}
return false;
}
function isMobile()
{
if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
return true;
}
if (isset($_SERVER['HTTP_VIA'])) {
return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
}
if (isset($_SERVER['HTTP_USER_AGENT'])) {
$acg = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
if (preg_match("/(" . implode('|', $acg) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
return true;
}
}
if (isset($_SERVER['HTTP_ACCEPT'])) {
if (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html'))) {
return true;
}
}
return false;
}
use Symfony\Component\Cache\Simple\FilesystemCache;
function cache($k, $df = null, $abn = 10, $ach = 0)
{
$aci = new FilesystemCache();
if ($df) {
if (is_callable($df)) {
if ($ach || !$aci->has($k)) {
$ab = $df();
debug("--------- fn: no cache for [{$k}] ----------");
$aci->set($k, $ab, $abn);
} else {
$ab = $aci->get($k);
debug("======= fn: data from cache [{$k}] ========");
}
} else {
debug("--------- set cache for [{$k}] ---------- : " . json_encode($df));
$aci->set($k, $df, $abn);
$ab = $df;
}
} else {
debug("--------- get cache for [{$k}] ---------- ");
$ab = $aci->get($k);
}
return $ab;
}
function cache_del($k)
{
$aci = new FilesystemCache();
$aci->delete($k);
debug("!!!!!!--------- delete cache for [{$k}] ----------!!!!!!");
}
function cache_clear()
{
$aci = new FilesystemCache();
$aci->clear();
debug("!!!!!!--------- clear all cache ----------!!!!!!");
}
function baseModel($acj)
{
return '<' . <<<EOF
?php
namespace Entities {
class {$acj}
{
    protected \$id;
    protected \$intm;
    protected \$st;

EOF;
}
function baseArray($acj, $ej)
{
return array("Entities\\{$acj}" => array('type' => 'entity', 'table' => $ej, 'id' => array('id' => array('type' => 'integer', 'id' => true, 'generator' => array('strategy' => 'IDENTITY'))), 'fields' => array('intm' => array('type' => 'datetime', 'column' => '_intm'), 'st' => array('type' => 'integer', 'column' => '_st', 'options' => array('default' => 1)))));
}
function genObj($acj)
{
$jl = array_merge(\db::col_array('sys_objects'), ['sys_object_item.name(itemname)', 'sys_object_item.colname', 'sys_object_item.type', 'sys_object_item.length', 'sys_object_item.default', 'sys_object_item.comment']);
$dt = ['[>]sys_object_item' => ['id' => 'oid']];
$eg = ['AND' => ['sys_objects.name' => $acj], 'ORDER' => ['sys_objects.id' => 'DESC']];
$dk = \db::all('sys_objects', $eg, $jl, $dt);
if ($dk) {
$ej = $dk[0]['table'];
$ab = baseArray($acj, $ej);
$ack = baseModel($acj);
foreach ($dk as $dv) {
if (!$dv['itemname']) {
continue;
}
$acl = $dv['colname'] ? $dv['colname'] : $dv['itemname'];
$jn = ['type' => "{$dv['type']}", 'column' => "{$acl}", 'options' => array('default' => "{$dv['default']}", 'comment' => "{$dv['comment']}")];
$ab['Entities\\' . $acj]['fields'][$dv['itemname']] = $jn;
$ack .= "    protected \${$dv['itemname']}; \n";
}
$ack .= '}}';
}
return [$ab, $ack];
}
function writeObjFile($acj)
{
list($ab, $ack) = genObj($acj);
$acm = \Symfony\Component\Yaml\Yaml::dump($ab);
$acn = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$aco = $acn . '/src/objs';
if (!is_dir($aco)) {
mkdir($aco, 0777, true);
}
file_put_contents("{$aco}/{$acj}.php", $ack);
file_put_contents("{$aco}/Entities.{$acj}.dcm.yml", $acm);
}
function sync_to_db($acp = 'run')
{
echo $acp;
$acn = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$acp = "cd {$acn} && sh ./{$acp}.sh";
exec($acp, $mx);
foreach ($mx as $ef) {
echo \SqlFormatter::format($ef);
}
}
function gen_schema($acq, $acr, $acs = false, $act = false)
{
$acu = true;
$acv = ROOT_PATH . '/tools/bin/db';
$acw = [$acv . "/yml", $acv . "/src/objs"];
$e = \Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration($acw, $acu);
$acx = \Doctrine\ORM\EntityManager::create($acq, $e);
$acy = $acx->getConnection()->getDatabasePlatform();
$acy->registerDoctrineTypeMapping('enum', 'string');
$acz = [];
foreach ($acr as $ade) {
$adf = $ade['name'];
include_once "{$acv}/src/objs/{$adf}.php";
$acz[] = $acx->getClassMetadata('Entities\\' . $adf);
}
$adg = new \Doctrine\ORM\Tools\SchemaTool($acx);
$adh = $adg->getUpdateSchemaSql($acz, true);
if (!$adh) {
}
$adi = [];
$adj = [];
foreach ($adh as $ef) {
if (startWith($ef, 'DROP')) {
$adi[] = $ef;
}
$adj[] = \SqlFormatter::format($ef);
}
if ($acs && !$adi || $act) {
$v = $adg->updateSchema($acz, true);
}
return $adj;
}
function gen_dbc_schema($acr)
{
$adk = \db::dbc();
$acq = ['driver' => 'pdo_mysql', 'host' => $adk['server'], 'port' => $adk['port'], 'user' => $adk['username'], 'password' => $adk['password'], 'dbname' => $adk['database_name']];
$acs = get('write', false);
$adl = get('force', false);
$adh = gen_schema($acq, $acr, $acs, $adl);
return ['database' => $adk['database_name'], 'sqls' => $adh];
}
function gen_corp_schema($cv, $acr)
{
\db::switch_dbc($cv);
return gen_dbc_schema($acr);
}
function buildcmd($ch = array())
{
$adm = new ptlis\ShellCommand\CommandBuilder();
$nr = ['LC_CTYPE=en_US.UTF-8'];
if (isset($ch['args'])) {
$nr = $ch['args'];
}
if (isset($ch['add_args'])) {
$nr = array_merge($nr, $ch['add_args']);
}
$adn = $adm->setCommand('/usr/bin/env')->addArguments($nr)->buildCommand();
return $adn;
}
function exec_git($ch = array())
{
$bt = '.';
if (isset($ch['path'])) {
$bt = $ch['path'];
}
$nr = ["/usr/bin/git", "--git-dir={$bt}/.git", "--work-tree={$bt}"];
$acp = 'status';
if (isset($ch['cmd'])) {
$acp = $ch['cmd'];
}
$nr[] = $acp;
$adn = buildcmd(['add_args' => $nr, $acp]);
$er = $adn->runSynchronous();
return $er->getStdOutLines();
}
use db\Rest as rest;
function getMetaData($acj, $ado = array())
{
ctx::pagesize(50);
$acr = db::all('sys_objects');
$adp = array_filter($acr, function ($bz) use($acj) {
return $bz['name'] == $acj;
});
$adp = array_shift($adp);
$adq = $adp['id'];
$adr = db::all('sys_object_item', ['oid' => $adq]);
$ads = ['Id'];
$adt = [0];
$adu = [0.1];
$ds = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true]];
foreach ($adr as $ef) {
$bv = $ef['name'];
$acl = $ef['colname'] ? $ef['colname'] : $bv;
$c = $ef['type'];
$my = $ef['default'];
$adv = $ef['col_width'];
$adw = $ef['readonly'] ? ture : false;
$adx = $ef['is_meta'];
if ($adx) {
$ads[] = $bv;
$adt[$acl] = $bv;
$adu[] = (double) $adv;
if (in_array($acl, array_keys($ado))) {
$ds[] = $ado[$acl];
} else {
$ds[] = ['data' => $acl, 'renderer' => 'html', 'readOnly' => $adw];
}
}
}
$ads[] = "InTm";
$ads[] = "St";
$adu[] = 60;
$adu[] = 10;
$ds[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$ds[] = ['data' => "_st", 'renderer' => "html"];
$kt = ['objname' => $acj];
return [$kt, $ads, $adt, $adu, $ds];
}
function getHotData($acj, $ado = array())
{
$ads[] = "InTm";
$ads[] = "St";
$adu[] = 60;
$adu[] = 10;
$ds[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$ds[] = ['data' => "_st", 'renderer' => "html"];
$kt = ['objname' => $acj];
return [$kt, $ads, $adu, $ds];
}
function fixfn($ck)
{
foreach ($ck as $cl) {
if (!function_exists($cl)) {
eval("function {$cl}(){}");
}
}
}
function cookie_test()
{
if (isset($_COOKIE['PHPSESSID'])) {
return true;
}
return false;
}
function ms($bv)
{
return \ctx::container()->ms->getService($bv);
}
function rms($bv, $x = 'rest')
{
return \ctx::container()->ms->getRest($bv, $x);
}
function idxtree($ady, $adz)
{
$jt = [];
$ab = \db::all($ady, ['pid' => $adz]);
$aef = getKeyValues($ab, 'id');
if ($aef) {
foreach ($aef as $adz) {
$jt = array_merge($jt, idxtree($ady, $adz));
}
}
return array_merge($aef, $jt);
}
function treelist($ady, $adz)
{
$or = \db::row($ady, ['id' => $adz]);
$aeg = $or['sub_ids'];
$aeg = json_decode($aeg, true);
$aeh = \db::all($ady, ['id' => $aeg]);
$aei = 0;
foreach ($aeh as $by => $aej) {
if ($aej['pid'] == $adz) {
$aeh[$by]['pid'] = 0;
$aei++;
}
}
if ($aei < 2) {
$aeh[] = [];
}
return $aeh;
return array_merge([$or], $aeh);
}
function switch_domain($ax, $cv)
{
$ak = cache($ax);
$ak['userinfo']['corpid'] = $cv;
cache_user($ax, $ak);
$aek = [];
$ael = ms('master');
if ($ael) {
$cw = ms('master')->get(['path' => '/master/corp/apps', 'data' => ['corpid' => $cv]]);
$aek = $cw->json();
$aek = getArg($aek, 'data');
}
return $aek;
}
function auto_reg_user($aem = 'username', $aen = 'password', $cy = 'user', $aeo = 0)
{
$cf = randstr(10);
$cg = randstr(6);
$ab = ["{$aem}" => $cf, "{$aen}" => $cg, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
if ($aeo) {
list($cg, $abu) = hashsalt($cg);
$ab[$aen] = $cg;
$ab['salt'] = $abu;
} else {
$ab[$aen] = md5($cg);
}
return db::save($cy, $ab);
}
function refresh_token($cy, $bf, $hl = '')
{
$aep = cguid();
$ab = ['id' => $bf, 'token' => $aep];
$ak = db::save($cy, $ab);
if ($hl) {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/', $hl);
} else {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/');
}
return $ak;
}
function local_user($aeq, $ku, $cy = 'user')
{
return \db::row($cy, [$aeq => $ku]);
}
function user_login($app, $aem = 'username', $aen = 'password', $cy = 'user', $aeo = 0)
{
$ab = ctx::data();
$ab = select_keys([$aem, $aen], $ab);
$cf = $ab[$aem];
$cg = $ab[$aen];
if (!$cf || !$cg) {
return NULL;
}
$ak = \db::row($cy, ["{$aem}" => $cf]);
if ($ak) {
if ($aeo) {
$abu = $ak['salt'];
list($cg, $abu) = hashsalt($cg, $abu);
} else {
$cg = md5($cg);
}
if ($cg == $ak[$aen]) {
return $ak;
}
}
return NULL;
}
function uc_auto_reg_user($fk, $aer)
{
$v = \uc::find_user(['username' => $fk]);
if ($v['code'] != 0) {
$v = uc::reg_user($fk, $aer);
} else {
$v = ['code' => 1, 'data' => []];
}
return $v;
}
function uc_login_data($bj)
{
$az = uc::user_info($bj);
$az = $az['data'];
$fy = [];
$aes = uc::user_role($bj, 1);
$ms = [];
if ($aes['code'] == 0) {
$ms = $aes['data']['roles'];
if ($ms) {
foreach ($ms as $k => $fw) {
$fy[] = $fw['name'];
}
}
}
$az['roles'] = $fy;
$aet = uc::user_domain($bj);
$az['corps'] = array_values($aet['data']);
return [$bj, $az, $ms];
}
function uc_user_login($app, $aem = 'username', $aen = 'password')
{
log_time("uc_user_login start");
$wy = $app->getContainer();
$z = $wy->request;
$ab = $z->getParams();
$ab = select_keys([$aem, $aen], $ab);
$cf = $ab[$aem];
$cg = $ab[$aen];
if (!$cf || !$cg) {
return NULL;
}
uc::init();
$v = uc::pwd_login($cf, $cg);
if ($v['code'] != 0) {
ret($v['code'], $v['message']);
}
$bj = $v['data']['access_token'];
return uc_login_data($bj);
}
function cube_user_login($app, $aem = 'username', $aen = 'password')
{
$wy = $app->getContainer();
$z = $wy->request;
$ab = $z->getParams();
if (isset($ab['code']) && isset($ab['bind_type'])) {
$e = getWxConfig('ucode');
$aeu = \EasyWeChat\Factory::miniProgram($e);
$aev = $aeu->auth->session($ab['code']);
$ab = cube::openid_login($aev['openid'], $ab['bind_type']);
} else {
$aew = select_keys([$aem, $aen], $ab);
$cf = $aew[$aem];
$cg = $aew[$aen];
if (!$cf || !$cg) {
return NULL;
}
$ab = cube::login($cf, $cg);
}
$ak = cube::user();
if (!$ak) {
return NULL;
}
$ak['user']['modules'] = cube::modules()['modules'];
$ak['passport'] = cube::$passport;
$fy = cube::roles()['roles'];
$aex = indexArray($fy, 'id');
$ms = [];
if ($ak['user']['roles']) {
foreach ($ak['user']['roles'] as &$aey) {
$aey['name'] = $aex[$aey['role_id']]['name'];
$aey['title'] = $aex[$aey['role_id']]['title'];
$aey['description'] = $aex[$aey['role_id']]['description'];
$ms[] = $aey['name'];
}
}
$ak['user']['role_list'] = $ak['user']['roles'];
$ak['user']['roles'] = $ms;
return $ak;
}
function check_auth($app)
{
$z = req();
$aez = false;
$afg = cfg::get('public_paths');
$gm = $z->getUri()->getPath();
if ($gm == '/') {
$aez = true;
} else {
foreach ($afg as $bt) {
if (startWith($gm, $bt)) {
$aez = true;
}
}
}
info("check_auth: {$aez} {$gm}");
if (!$aez) {
if (is_weixin()) {
$hm = $_SERVER['REQUEST_URI'];
header('Location: /api/auth/wechat?_r=' . $hm);
}
ret(1, 'auth error');
}
}
function extractUserData($afh)
{
return ['githubLogin' => $afh['login'], 'githubName' => $afh['name'], 'githubId' => $afh['id'], 'repos_url' => $afh['repos_url'], 'avatar_url' => $afh['avatar_url'], '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
}
function output_user($ak, $afi = false)
{
unset($ak['passwd']);
unset($ak['salt']);
if (!$afi) {
unset($ak['token']);
}
unset($ak['access-token']);
ret($ak);
}
function cache_user($ax, $ak = null)
{
cache($ax, $ak, 7200, $ak);
$az = null;
if ($ak) {
$az = getArg($ak, 'userinfo');
}
return ['token' => $ax, 'userinfo' => $az];
}
if (!isset($_SERVER['REQUEST_METHOD'])) {
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/pub/psysh';
}
$app = new \Slim\App();
ctx::app($app);
function tpl($br, $afj = '.html')
{
$br = $br . $afj;
$afk = cfg::get('tpl_prefix');
$afl = "{$afk['pc']}/{$br}";
$afm = "{$afk['mobile']}/{$br}";
info("tpl: {$afl} | {$afm}");
return isMobile() ? $afm : $afl;
}
function req()
{
return ctx::req();
}
function get($bv, $my = '')
{
$z = req();
$u = $z->getParam($bv, $my);
if ($u == $my) {
$afn = ctx::gets();
if (isset($afn[$bv])) {
return $afn[$bv];
}
}
return $u;
}
function post($bv, $my = '')
{
$z = req();
return $z->getParam($bv, $my);
}
function gets()
{
$z = req();
$v = $z->getQueryParams();
$v = array_merge($v, ctx::gets());
return $v;
}
function querystr()
{
$z = req();
return $z->getUri()->getQuery();
}
function posts()
{
$z = req();
return $z->getParsedBody();
}
function reqs()
{
$z = req();
return $z->getParams();
}
function uripath()
{
$z = req();
$gm = $z->getUri()->getPath();
if (!startWith($gm, '/')) {
$gm = '/' . $gm;
}
return $gm;
}
function host_str($uw)
{
$afo = '';
if (isset($_SERVER['HTTP_HOST'])) {
$afo = $_SERVER['HTTP_HOST'];
}
return " [ {$afo} ] " . $uw;
}
function debug($uw)
{
$m = \cfg::get('app');
if (isset($m['log_level']) && $m['log_level'] == 100) {
if (ctx::logger()) {
$uw = format_log_str($uw, getCallerStr(3));
ctx::logger()->debug(host_str($uw));
}
}
}
function warn($uw)
{
if (ctx::logger()) {
$uw = format_log_str($uw, getCallerStr(3));
ctx::logger()->warn(host_str($uw));
}
}
function info($uw)
{
if (ctx::logger()) {
$uw = format_log_str($uw, getCallerStr(3));
ctx::logger()->info(host_str($uw));
}
}
function format_log_str($uw, $afp = '')
{
if (is_array($uw)) {
$uw = json_encode($uw);
}
return "{$uw} [ ::{$afp} ]";
}
function ck_owner($ef)
{
$bf = ctx::uid();
$kz = $ef['uid'];
debug("ck_owner: {$bf} {$kz}");
return $bf == $kz;
}
function _err($bv)
{
return cfg::get($bv, 'error');
}
$__log_time__ = 0;
$__log_begin_time__ = 0;
function log_time($bx = '', $aby = 0)
{
global $__log_time__, $__log_begin_time__;
list($abm, $abn) = explode(" ", microtime());
$afq = (double) $abm + (double) $abn;
if (!$__log_time__) {
$__log_begin_time__ = $afq;
$__log_time__ = $afq;
$bt = uripath();
debug("usetime: --- {$bt} ---");
return $afq;
}
if ($aby && $aby == 'begin') {
$afr = $__log_begin_time__;
} else {
$afr = $aby ? $aby : $__log_time__;
}
$acd = $afq - $afr;
$acd *= 1000;
debug("usetime: ---  {$acd} {$bx}  ---");
$__log_time__ = $afq;
return $afq;
}
use core\Service as ms;
$p = $app->getContainer();
$p['view'] = function ($wy) {
$bs = new \Slim\Views\Twig(ROOT_PATH . '/templates', ['cache' => false]);
$bs->addExtension(new \Slim\Views\TwigExtension($wy['router'], $wy['request']->getUri()));
return $bs;
};
$p['logger'] = function ($wy) {
if (is_docker_env()) {
$afs = '/ws/log/app.log';
} else {
$aft = cfg::get('logdir');
if ($aft) {
$afs = $aft . '/app.log';
} else {
$afs = __DIR__ . '/../app.log';
}
}
$afu = ['name' => '', 'path' => $afs];
$afv = new \Monolog\Logger($afu['name']);
$afv->pushProcessor(new \Monolog\Processor\UidProcessor());
$afw = \cfg::get('app');
$ow = isset($afw['log_level']) ? $afw['log_level'] : '';
if (!$ow) {
$ow = \Monolog\Logger::INFO;
}
$afv->pushHandler(new \Monolog\Handler\StreamHandler($afu['path'], $ow));
return $afv;
};
log_time();
unset($app->getContainer()['phpErrorHandler']);
unset($app->getContainer()['errorHandler']);
$p['notFoundHandler'] = function ($wy) {
if (!\ctx::isFoundRoute()) {
return function ($gj, $gk) use($wy) {
return $wy['response']->withStatus(404)->withHeader('Content-Type', 'text/html')->write('Page not found');
};
}
return function ($gj, $gk) use($wy) {
return $wy['response'];
};
};
$p['ms'] = function ($wy) {
ms::init();
return new ms();
};
\Valitron\Validator::addRule('mobile', function ($jn, $l, array $bc) {
return preg_match("/^1[3|4|5|7|8]\\d{9}\$/", $l) ? TRUE : FALSE;
}, 'must be mobile number');
log_time("DEPS END");
log_time("ROUTES BEGIN");
use Lead\Dir\Dir as dir;
$afx = ROOT_PATH . '/routes';
if (folder_exist($afx)) {
$q = dir::scan($afx, ['type' => 'file']);
foreach ($q as $r) {
if (basename($r) != 'routes.php' && !endWith($r, '.DS_Store')) {
require_once $r;
}
}
}
$afy = cfg::get('opt_route_list');
if ($afy) {
foreach ($afy as $aj) {
info("def route {$aj}");
$app->options($aj, function () {
ret([]);
});
}
}
$app->get('/route_list', function () {
debug("======= route list ========");
sendJSON(ctx::route_list());
});
$app->get('/cache/clear', function () {
debug("======= clear cache ========");
cache_clear();
sendJSON([]);
});
if (cfg::get('enable_blockly')) {
$afz = cache('blockly_routes_key', function () {
return db::all('sys_blockly', ['AND' => ['code_type' => 'route']]);
}, 86400, 1);
foreach ($afz as $agh) {
$agi = get('nb');
if ($agi != 1) {
@eval($agh['phpcode']);
}
}
}
log_time("ROUTES ENG");
}
namespace {
use db\Rest as rest;
function def_hot_rest($app, $bv, $el = array())
{
$app->options("/hot/{$bv}", function () {
ret([]);
});
$app->options("/hot/{$bv}/{id}", function () {
ret([]);
});
$app->get("/hot/{$bv}", function () use($el, $bv) {
$acj = $el['objname'];
$agj = $bv;
$dk = rest::getList($agj);
$ado = isset($el['cols_map']) ? $el['cols_map'] : [];
list($kt, $ads, $adt, $adu, $ds) = getMetaData($acj, $ado);
$adu[0] = 10;
$v['data'] = ['meta' => $kt, 'list' => $dk['data'], 'colHeaders' => $ads, 'colWidths' => $adu, 'cols' => $ds];
ret($v);
});
$app->get("/hot/{$bv}/param", function () use($el, $bv) {
$acj = $el['objname'];
$agj = $bv;
$dk = rest::getList($agj);
list($ads, $agk, $adt, $adu, $ds, $agl) = getHotColMap1($agj, ['param_pid' => $dk['data'][0]['id']]);
$kt = ['objname' => $acj];
$adu[0] = 10;
$v['data'] = ['meta' => $kt, 'list' => [], 'colHeaders' => $ads, 'colHeaderDatas' => $adt, 'colHeaderGroupDatas' => $agk, 'colWidths' => $adu, 'cols' => $ds, 'origin_data' => $agl];
ret($v);
});
$app->post("/hot/{$bv}", function () use($el, $bv) {
$agj = $bv;
$dk = rest::postData($agj);
ret($dk);
});
$app->put("/hot/{$bv}/{id}", function ($z, $bp, $nr) use($el, $bv) {
$agj = $bv;
$ab = ctx::data();
if (isset($ab['trans-word']) && isset($ab[$ab['trans-from']])) {
$agm = $ab['trans-from'];
$agn = $ab['trans-to'];
$u = util\Pinyin::get($ab[$agm]);
$ab[$agn] = $u;
}
ctx::data($ab);
$dk = rest::putData($agj, $nr['id']);
ret($dk);
});
}
function getHotColMap1($agj, $ch = array())
{
$ago = get('pname', '_param');
$agp = get('oname', '_opt');
$agq = get('ename', '_opt_ext');
$agr = get('lname', 'label');
$ags = getArg($ch, 'param_pid', 0);
$agt = $agj . $ago;
$agu = $agj . $agp;
$agv = $agj . $agq;
ctx::pagesize(50);
if ($ags) {
ctx::gets('pid', $ags);
}
$dk = rest::getList($agt, $ch);
$agw = getKeyValues($dk['data'], 'id');
$bc = indexArray($dk['data'], 'id');
$ch = db::all($agu, ['AND' => ['pid' => $agw]]);
$ch = indexArray($ch, 'id');
$agw = array_keys($ch);
$agx = db::all($agv, ['AND' => ['pid' => $agw]]);
$agx = groupArray($agx, 'pid');
$agy = getParamOptExt($bc, $ch, $agx);
$ads = [];
$adt = [];
$agk = [];
$adu = [];
$ds = [];
foreach ($bc as $k => $agz) {
$ads[] = $agz[$agr];
$agk[$agz['name']] = $agz['group_name'] ? $agz['group_name'] : $agz[$agr];
$adt[$agz['name']] = $agz[$agr];
$adu[] = $agz['width'];
$ds[$agz['name']] = ['data' => $agz['name'], 'renderer' => 'html'];
}
foreach ($agx as $k => $ez) {
$ahi = '';
$adz = 0;
$ahj = $ch[$k];
$ahk = $ahj['pid'];
$agz = $bc[$ahk];
$ahl = $agz[$agr];
$ahi = $agz['name'];
$ahm = $agz['type'];
if ($adz) {
}
if ($ahi) {
$dg = ['data' => $ahi, 'type' => 'autocomplete', 'strict' => false, 'source' => array_values(getKeyValues($ez, 'option'))];
if ($ahm == 'select2') {
$dg['editor'] = 'select2';
$ahn = [];
foreach ($ez as $aho) {
$ef['id'] = $aho['id'];
$ef['text'] = $aho['option'];
$ahn[] = $ef;
}
$dg['select2Options'] = ['data' => $ahn, 'dropdownAutoWidth' => true, 'width' => 'resolve'];
unset($dg['type']);
}
$ds[$ahi] = $dg;
}
}
$ds = array_values($ds);
return [$ads, $agk, $adt, $adu, $ds, $agy];
}
function getParamOptExt($bc, $ch, $agx)
{
$el = [];
$ahp = [];
foreach ($agx as $k => $ahq) {
$ahj = $ch[$k];
$agp = $ahj['name'];
$ahk = $ahj['pid'];
foreach ($ahq as $aho) {
$ahr = $aho['_rownum'];
$el[$ahk][$ahr][$agp] = $aho['option'];
$ahp[$ahk][$ahr][$agp] = $aho;
}
}
foreach ($ahp as $bw => $aho) {
$bc[$bw]['opt_exts'] = array_values($aho);
}
foreach ($el as $bw => $ef) {
$bc[$bw]['options'] = array_values($ef);
}
$ab = array_values($bc);
return $ab;
}
}
namespace {
use db\Rest as rest;
use util\Pinyin;
function def_hot_opt_rest($app, $bv, $el = array())
{
$agj = $bv;
$ahs = "{$bv}_ext";
$app->get("/hot/{$bv}", function () use($agj, $ahs) {
$lo = get('oid');
$adz = get('pid');
$de = "select * from `{$agj}` pp join `{$ahs}` pv\n              on pp.id = pv.`pid`\n              where pp.oid={$lo} and pp.pid={$adz}";
$dk = db::query($de);
$ab = groupArray($dk, 'name');
$ads = ['Id', 'Oid', 'RowNum'];
$adu = [5, 5, 5];
$ds = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true], ['data' => 'oid', 'renderer' => 'html', 'readOnly' => true], ['data' => '_rownum', 'renderer' => 'html', 'readOnly' => true]];
$ai = [];
foreach ($ab as $by => $bz) {
$ads[] = $bz[0]['label'];
$adu[] = $bz[0]['col_width'];
$ds[] = ['data' => $by, 'renderer' => 'html'];
$aht = [];
foreach ($bz as $k => $ef) {
$ai[$ef['_rownum']][$by] = $ef['option'];
if ($by == 'value') {
if (!isset($ai[$ef['_rownum']]['id'])) {
$ai[$ef['_rownum']]['id'] = $ef['id'];
$ai[$ef['_rownum']]['oid'] = $lo;
$ai[$ef['_rownum']]['_rownum'] = $ef['_rownum'];
}
}
}
}
$ai = array_values($ai);
$v['data'] = ['list' => $ai, 'colHeaders' => $ads, 'colWidths' => $adu, 'cols' => $ds];
ret($v);
});
$app->get("/hot/{$bv}_addprop", function () use($agj, $ahs) {
$lo = get('oid');
$adz = get('pid');
$ahu = get('propname');
if ($ahu != 'value' && !checkOptPropVal($lo, $adz, 'value', $agj, $ahs)) {
addOptProp($lo, $adz, 'value', $agj, $ahs);
}
if (!checkOptPropVal($lo, $adz, $ahu, $agj, $ahs)) {
addOptProp($lo, $adz, $ahu, $agj, $ahs);
}
ret([11]);
});
$app->options("/hot/{$bv}", function () {
ret([]);
});
$app->options("/hot/{$bv}/{id}", function () {
ret([]);
});
$app->post("/hot/{$bv}", function () use($agj, $ahs) {
$ab = ctx::data();
$adz = $ab['pid'];
$lo = $ab['oid'];
$ahv = getArg($ab, '_rownum');
$ahw = db::row($agj, ['AND' => ['oid' => $lo, 'pid' => $adz, 'name' => 'value']]);
if (!$ahw) {
addOptProp($lo, $adz, 'value', $agj, $ahs);
}
$ahx = $ahw['id'];
$ahy = db::obj()->max($ahs, '_rownum', ['pid' => $ahx]);
$ab = ['oid' => $lo, 'pid' => $ahx, '_rownum' => $ahy + 1];
db::save($ahs, $ab);
$v = ['oid' => $lo, '_rownum' => $ahv, 'prop' => $ahw, 'maxrow' => $ahy];
ret($v);
});
$app->put("/hot/{$bv}/{id}", function ($z, $bp, $nr) use($ahs, $agj) {
$ab = ctx::data();
$adz = $ab['pid'];
$lo = $ab['oid'];
$ahv = $ab['_rownum'];
$ahv = getArg($ab, '_rownum');
$ax = $ab['token'];
$bf = $ab['uid'];
$ef = dissoc($ab, ['oid', 'pid', '_rownum', '_uptm', 'uniqid', 'token', 'uid']);
debug($ef);
$k = key($ef);
$u = $ef[$k];
$ahw = db::row($agj, ['AND' => ['pid' => $adz, 'oid' => $lo, 'name' => $k]]);
info("{$adz} {$lo} {$k}");
$ahx = $ahw['id'];
$ahz = db::obj()->has($ahs, ['AND' => ['pid' => $ahx, '_rownum' => $ahv]]);
if ($ahz) {
debug("has cell ...");
$de = "update {$ahs} set `option`='{$u}' where _rownum={$ahv} and pid={$ahx}";
debug($de);
db::exec($de);
} else {
debug("has no cell ...");
$ab = ['oid' => $lo, 'pid' => $ahx, '_rownum' => $ahv, 'option' => $u];
db::save($ahs, $ab);
}
$v = ['item' => $ef, 'oid' => $lo, '_rownum' => $ahv, 'key' => $k, 'val' => $u, 'prop' => $ahw, 'sql' => $de];
ret($v);
});
}
function checkOptPropVal($lo, $adz, $bv, $agj, $ahs)
{
return db::obj()->has($agj, ['AND' => ['name' => $bv, 'oid' => $lo, 'pid' => $adz]]);
}
function addOptProp($lo, $adz, $ahu, $agj, $ahs)
{
$bv = Pinyin::get($ahu);
$ab = ['oid' => $lo, 'pid' => $adz, 'label' => $ahu, 'name' => $bv];
$ahw = db::save($agj, $ab);
$ab = ['_rownum' => 1, 'oid' => $lo, 'pid' => $ahw['id']];
db::save($ahs, $ab);
return $ahw;
}
}
namespace {
log_time("MID BEGIN");
$app->add(new \mid\TwigMid());
$app->add(new \mid\RestMid());
$aij = \cfg::load('mid');
if ($aij) {
foreach ($aij as $by => $m) {
$aik = "\\{$by}";
debug("load mid: {$aik}");
$app->add(new $aik());
}
}
if (file_exists(ROOT_PATH . DS . 'lib/mid/MyAuthMid.php')) {
$app->add(new \mid\MyAuthMid());
} else {
$app->add(new \mid\AuthMid());
}
$app->add(new \pavlakis\cli\CliRequest());
log_time("MID END");
}
namespace {
session_start();
$app->run();
}