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
return self::$_data;
}
public static function src_data()
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
public static function pagesize($ao = null)
{
if ($ao) {
self::$_page['pagesize'] = $ao;
}
return self::$_page['pagesize'];
}
public static function offset()
{
return self::$_page['offset'];
}
public static function count($ap)
{
self::$_page['count'] = $ap;
$aq = $ap / self::$_page['pagesize'];
$aq = ceil($aq);
self::$_page['totalpage'] = $aq;
if (self::$_page['page'] == '1') {
self::$_page['isFirstPage'] = true;
}
if (!$aq || self::$_page['page'] == $aq) {
self::$_page['isLastPage'] = true;
}
if (self::$_page['nextpage'] > $aq) {
self::$_page['nextpage'] = $aq ? $aq : 1;
}
$ar = self::$_page['page'] - 4;
$as = self::$_page['page'] + 4;
if ($as > $aq) {
$as = $aq;
$ar = $ar - ($as - $aq);
}
if ($ar <= 1) {
$ar = 1;
}
if ($as - $ar < 8 && $as < $aq) {
$as = $ar + 8;
}
$as = $as ? $as : 1;
$ai = range($ar, $as);
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
$at = isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : '';
$at = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : $at;
$ab = '';
if (!empty($_POST)) {
$ab = $_POST;
} else {
$au = file_get_contents("php://input");
if ($au) {
if (strpos($at, 'application/x-www-form-urlencoded') !== false) {
parse_str($au, $av);
$ab = $av;
} else {
if (strpos($at, 'application/json') !== false) {
$ab = json_decode($au, true);
}
}
}
}
return $ab;
}
public static function getToken($z, $k = 'token')
{
$aw = $z->getParam($k);
$ab = $z->getParams();
if (!$aw) {
$aw = getArg($ab, $k);
}
if (!$aw) {
$aw = getArg($_COOKIE, $k);
}
return $aw;
}
public static function token()
{
return self::$_token;
}
public static function getUcTokenUser($aw)
{
if (!$aw) {
return null;
}
$ax = cache($aw);
$ay = $ax['userinfo'] ? $ax['userinfo'] : null;
if (isset($ax['luser'])) {
$ay['id'] = $ay['uid'] = $ax['luser']['id'];
return $ay;
}
return null;
}
public static function getCubeTokenUser($aw)
{
if (!$aw) {
return null;
}
$ax = cache($aw);
$ak = $ax['user'] ? $ax['user'] : null;
if (isset($ax['luser'])) {
$ak['id'] = $ak['uid'] = $ax['luser']['id'];
return $ak;
}
return null;
}
public static function getAuthType($az, $aw)
{
if (isset($az['auth_type'])) {
return $az['auth_type'];
}
$bc = explode('$$', $aw);
if (count($bc) == 2) {
return $bc[1];
}
return null;
}
public static function getTokenUser($bd, $z)
{
$be = $z->getParam('uid');
$ak = null;
$az = $z->getParams();
$bf = self::check_appid($az);
if ($bf && check_sign($az, $bf)) {
debug("appkey: {$bf}");
$ak = ['id' => $be, 'roles' => ['admin']];
} else {
if (self::isStateless()) {
debug("isStateless");
$ak = ['id' => $be, 'role' => 'user'];
} else {
$aw = self::$_token;
$bg = \cfg::get('use_ucenter_oauth');
if ($bh = self::getAuthType($az, $aw)) {
if ($bh == 'cube') {
return self::getCubeTokenUser($aw);
}
}
if ($bg) {
return self::getUcTokenUser($aw);
}
$bi = self::getToken($z, 'access_token');
if (self::isEnableSso()) {
debug("getTokenUserBySso");
$ak = self::getTokenUserBySso($aw);
} else {
debug("get from db");
if ($aw) {
$bj = \cfg::get('disable_cache_user');
if ($bj) {
$ak = \db::row($bd, ['token' => $aw]);
} else {
$ak = cache($aw);
$ak = $ak['user'];
}
} else {
if ($bi) {
$ak = self::getAccessTokenUser($bd, $bi);
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
public static function check_appid($az)
{
$bk = getArg($az, 'appid');
if ($bk) {
$m = cfg::get('support_service_list', 'service');
if (isset($m[$bk])) {
debug("appid: {$bk} ok");
return $m[$bk];
}
}
debug("appid: {$bk} not ok");
return '';
}
public static function getTokenUserBySso($aw)
{
$ak = ms('sso')->getuserinfo(['token' => $aw])->json();
return $ak;
}
public static function getAccessTokenUser($bd, $bi)
{
$bl = \db::row('oauth_access_tokens', ['access_token' => $bi]);
if ($bl) {
$bm = strtotime($bl['expires']);
if ($bm - time() > 0) {
$ak = \db::row($bd, ['id' => $bl['user_id']]);
}
}
return $ak;
}
public static function user_tbl($bn = null)
{
if ($bn) {
self::$_user_tbl = $bn;
}
return self::$_user_tbl;
}
public static function render($bo, $bp, $bq, $ab)
{
$br = new \Slim\Views\Twig($bp, ['cache' => false]);
self::$_foundRoute = true;
return $br->render($bo, $bq, $ab);
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
$bs = str_replace(self::$_rest_prefix, '', self::uri());
$bt = explode('/', $bs);
$bu = getArg($bt, 1, '');
$bv = getArg($bt, 2, '');
return [$bu, $bv];
}
public static function rest_select_add($bw = '')
{
if ($bw) {
self::$_rest_select_add = $bw;
}
return self::$_rest_select_add;
}
public static function rest_join_add($bw = '')
{
if ($bw) {
self::$_rest_join_add = $bw;
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
public static function gets($bx = '', $by = '')
{
if (!$bx) {
return self::$_gets;
}
if (!$by) {
return self::$_gets[$bx];
}
if ($by == '_clear') {
$by = '';
}
self::$_gets[$bx] = $by;
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
$bz = $m['endpoint'];
$cd = new \Gql\Client($bz, ['verify' => false]);
self::$client = $cd;
self::$cid = $m['client_id'];
self::$cst = $m['client_secret'];
}
return self::$client;
}
public static function login($ce, $cf)
{
self::client();
$cg = ['args' => ['client_id' => self::$cid, 'client_secret' => self::$cst, 'username' => $ce, 'password' => $cf], "resp" => ["access_token", "token_type", 'expires_in', 'refresh_token']];
$ab = self::client()->query('passport', $cg);
$ab = $ab['data'];
self::$passport = $ab['passport'];
return $ab;
}
public static function openid_login($ch, $ci = 'wechat')
{
self::client();
$cg = ['args' => ['client_id' => self::$cid, 'client_secret' => self::$cst, 'openid' => $ch, 'bind_type' => $ci], "resp" => ["access_token", "token_type", 'expires_in', 'refresh_token']];
$ab = self::client()->query('passport', $cg);
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
function fixfn($cj)
{
foreach ($cj as $ck) {
if (!function_exists($ck)) {
eval("function {$ck}(){}");
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
$cj = array('debug');
fixfn($cj);
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
public static function init($m, $cl = true)
{
self::init_db($m, $cl);
}
public static function conns()
{
$cm['_db'] = self::queryRow('select user() as user, database() as dbname');
self::use_master_db();
$cm['_db_master'] = self::queryRow('select user() as user, database() as dbname');
self::use_default_db();
$cm['_db_default'] = self::queryRow('select user() as user, database() as dbname');
return $cm;
}
public static function new_db($m)
{
return new Medoo($m);
}
public static function get_db_cfg($m = 'use_db')
{
$cn = \cfg::get('dbc_type', 'db.yml');
if (!$cn) {
$cn = 'default';
}
if ($cn == 'default') {
if (is_string($m)) {
$m = \cfg::get_db_cfg($m);
}
$m['database_name'] = env('DB_NAME', $m['database_name']);
$m['server'] = env('DB_HOST', $m['server']);
$m['username'] = env('DB_USER', $m['username']);
$m['password'] = env('DB_PASS', $m['password']);
} else {
if ($cn == 'domain') {
$co = self::get_dbc_domain_key();
$cp = self::get_dbc_domain_map();
$m = $cp[$co];
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
$cq = cache('dbc_domain_map', function () {
$cr = \cfg::get('dbc_domain_api', 'db.yml');
$cs = file_get_contents($cr);
$ab = json_decode($cs, true);
return $ab['data'];
}, 600);
return $cq;
}
public static function init_db($m, $cl = true)
{
self::$_dbc = self::get_db_cfg($m);
$ct = self::$_dbc['database_name'];
self::$_dbc_list[$ct] = self::$_dbc;
self::$_db_list[$ct] = self::new_db(self::$_dbc);
if ($cl) {
self::use_db($ct);
}
}
public static function use_db($ct)
{
self::$_db = self::$_db_list[$ct];
self::$_dbc = self::$_dbc_list[$ct];
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
public static function switch_dbc($cu)
{
$cv = ms('master')->get(['path' => '/admin/corpins', 'data' => ['corpid' => $cu]]);
$cw = $cv->json();
$cw = getArg($cw, 'data', []);
self::$_dbc = $cw;
self::$_db = self::$_db_default = self::new_db(self::$_dbc);
}
public static function obj()
{
if (!self::$_db) {
self::$_dbc = self::$_dbc_master = self::get_db_cfg();
self::$_db = self::$_db_default = self::$_db_master = self::new_db(self::$_dbc);
info('====== init dbc =====');
$aw = \ctx::getToken(req());
$ak = \ctx::getUcTokenUser($aw);
$cu = getArg($ak, 'corpid');
if ($cu) {
self::switch_dbc($cu);
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
public static function desc_sql($cx)
{
if (self::db_type() == 'mysql') {
return "desc `{$cx}`";
} else {
if (self::db_type() == 'pgsql') {
return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$cx}'";
} else {
return '';
}
}
}
public static function table_cols($bu)
{
$cy = self::$tbl_desc;
if (!isset($cy[$bu])) {
$cz = self::desc_sql($bu);
if ($cz) {
$cy[$bu] = self::query($cz);
self::$tbl_desc = $cy;
debug("---------------- cache not found : {$bu}");
} else {
debug("empty desc_sql for: {$bu}");
}
}
if (!isset($cy[$bu])) {
return array();
} else {
return self::$tbl_desc[$bu];
}
}
public static function col_array($bu)
{
$de = function ($by) use($bu) {
return $bu . '.' . $by;
};
return getKeyValues(self::table_cols($bu), 'Field', $de);
}
public static function valid_table_col($bu, $df)
{
$dg = self::table_cols($bu);
foreach ($dg as $dh) {
if ($dh['Field'] == $df) {
$c = $dh['Type'];
return is_string_column($dh['Type']);
}
}
return false;
}
public static function tbl_data($bu, $ab)
{
$dg = self::table_cols($bu);
$v = [];
foreach ($dg as $dh) {
$di = $dh['Field'];
if (isset($ab[$di])) {
$v[$di] = $ab[$di];
}
}
return $v;
}
public static function test()
{
$cz = "select * from tags limit 10";
$dj = self::obj()->query($cz)->fetchAll(\PDO::FETCH_ASSOC);
var_dump($dj);
}
public static function has_st($bu, $dk)
{
$dl = '_st';
return isset($dk[$dl]) || isset($dk[$bu . '.' . $dl]);
}
public static function getWhere($bu, $dm)
{
$dl = '_st';
if (!self::valid_table_col($bu, $dl)) {
return $dm;
}
$dl = $bu . '._st';
if (is_array($dm)) {
$dn = array_keys($dm);
$do = preg_grep("/^AND\\s*#?\$/i", $dn);
$dp = preg_grep("/^OR\\s*#?\$/i", $dn);
$dq = array_diff_key($dm, array_flip(explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')));
$dk = [];
if ($dq != array()) {
$dk = $dq;
if (!self::has_st($bu, $dk)) {
$dm[$dl] = 1;
$dm = ['AND' => $dm];
}
}
if (!empty($do)) {
$l = array_values($do);
$dk = $dm[$l[0]];
if (!self::has_st($bu, $dk)) {
$dm[$l[0]][$dl] = 1;
}
}
if (!empty($dp)) {
$l = array_values($dp);
$dk = $dm[$l[0]];
if (!self::has_st($bu, $dk)) {
$dm[$l[0]][$dl] = 1;
}
}
if (!isset($dm['AND']) && !self::has_st($bu, $dk)) {
$dm['AND'][$dl] = 1;
}
}
return $dm;
}
public static function all_sql($bu, $dm = array(), $dr = '*', $ds = null)
{
$cq = [];
if ($ds) {
$cz = self::obj()->selectContext($bu, $cq, $ds, $dr, $dm);
} else {
$cz = self::obj()->selectContext($bu, $cq, $dr, $dm);
}
return $cz;
}
public static function all($bu, $dm = array(), $dr = '*', $ds = null)
{
$dm = self::getWhere($bu, $dm);
info($dm);
if ($ds) {
$dj = self::obj()->select($bu, $ds, $dr, $dm);
} else {
$dj = self::obj()->select($bu, $dr, $dm);
}
return $dj;
}
public static function count($bu, $dm = array('_st' => 1))
{
$dm = self::getWhere($bu, $dm);
return self::obj()->count($bu, $dm);
}
public static function row_sql($bu, $dm = array(), $dr = '*', $ds = '')
{
return self::row($bu, $dm, $dr, $ds, true);
}
public static function row($bu, $dm = array(), $dr = '*', $ds = '', $dt = null)
{
$dm = self::getWhere($bu, $dm);
if (!isset($dm['LIMIT'])) {
$dm['LIMIT'] = 1;
}
if ($ds) {
if ($dt) {
return self::obj()->selectContext($bu, $ds, $dr, $dm);
}
$dj = self::obj()->select($bu, $ds, $dr, $dm);
} else {
if ($dt) {
return self::obj()->selectContext($bu, $dr, $dm);
}
$dj = self::obj()->select($bu, $dr, $dm);
}
if ($dj) {
return $dj[0];
} else {
return null;
}
}
public static function one($bu, $dm = array(), $dr = '*', $ds = '')
{
$du = self::row($bu, $dm, $dr, $ds);
$dv = '';
if ($du) {
$dw = array_keys($du);
$dv = $du[$dw[0]];
}
return $dv;
}
public static function parseUk($bu, $dx, $ab)
{
$dy = true;
info("uk: {$dx}, " . json_encode($ab));
if (is_array($dx)) {
foreach ($dx as $dz) {
if (!isset($ab[$dz])) {
$dy = false;
} else {
$ef[$dz] = $ab[$dz];
}
}
} else {
if (!isset($ab[$dx])) {
$dy = false;
} else {
$ef = [$dx => $ab[$dx]];
}
}
$eg = false;
if ($dy) {
info("has uk {$dy}");
info("where: " . json_encode($ef));
if (!self::obj()->has($bu, ['AND' => $ef])) {
$eg = true;
}
} else {
$eg = true;
}
return [$ef, $eg];
}
public static function save($bu, $ab, $dx = 'id')
{
list($ef, $eg) = self::parseUk($bu, $dx, $ab);
info("isInsert: {$eg}, {$bu} {$dx} " . json_encode($ab));
if ($eg) {
debug("insert {$bu} : " . json_encode($ab));
$eh = self::obj()->insert($bu, $ab);
$ab['id'] = self::obj()->id();
} else {
debug("update {$bu} " . json_encode($ef));
$eh = self::obj()->update($bu, $ab, ['AND' => $ef]);
}
if ($eh->errorCode() !== '00000') {
info($eh->errorInfo());
}
return $ab;
}
public static function update($bu, $ab, $dm)
{
self::obj()->update($bu, $ab, $dm);
}
public static function exec($cz)
{
return self::obj()->exec($cz);
}
public static function query($cz)
{
return self::obj()->query($cz)->fetchAll(\PDO::FETCH_ASSOC);
}
public static function queryRow($cz)
{
$dj = self::query($cz);
if ($dj) {
return $dj[0];
} else {
return null;
}
}
public static function queryOne($cz)
{
$du = self::queryRow($cz);
return self::oneVal($du);
}
public static function oneVal($du)
{
$dv = '';
if ($du) {
$dw = array_keys($du);
$dv = $du[$dw[0]];
}
return $dv;
}
public static function updateBatch($bu, $ab, $dx = 'id')
{
$ei = $bu;
if (!is_array($ab) || empty($ei)) {
return FALSE;
}
$cz = "UPDATE `{$ei}` SET";
foreach ($ab as $bv => $du) {
foreach ($du as $k => $u) {
$ej[$k][] = "WHEN {$bv} THEN {$u}";
}
}
foreach ($ej as $k => $u) {
$cz .= ' `' . trim($k, '`') . '`=CASE ' . $dx . ' ' . join(' ', $u) . ' END,';
}
$cz = trim($cz, ',');
$cz .= ' WHERE ' . $dx . ' IN(' . join(',', array_keys($ab)) . ')';
return self::query($cz);
}
}
class fcache
{
const FILE_LIFE_KEY = 'FILE_LIFE_KEY';
const CLEAR_ALL_KEY = 'CLEAR_ALL';
static $_instance = null;
protected $_options = array('cache_dir' => './cache', 'file_locking' => true, 'file_name_prefix' => 'cache', 'cache_file_umask' => 0777, 'file_life' => 100000);
static function &getInstance($ek = array())
{
if (self::$_instance === null) {
self::$_instance = new self($ek);
}
return self::$_instance;
}
static function &setOptions($ek = array())
{
return self::getInstance($ek);
}
private function __construct($ek = array())
{
if ($this->_options['cache_dir'] !== null) {
$bp = rtrim($this->_options['cache_dir'], '/') . '/';
$this->_options['cache_dir'] = $bp;
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
$el =& self::getInstance();
if (!is_dir($l)) {
exit('file_cache: ' . $l . ' 不是一个有效路径 ');
}
if (!is_writable($l)) {
exit('file_cache: 路径 "' . $l . '" 不可写');
}
$l = rtrim($this->_options['cache_dir'], '/') . '/';
$el->_options['cache_dir'] = $l;
}
static function save($ab, $bv = null, $em = null)
{
$el =& self::getInstance();
if (!$bv) {
if ($el->_id) {
$bv = $el->_id;
} else {
exit('file_cache:save() id 不能为空!');
}
}
$en = time();
if ($em) {
$ab[self::FILE_LIFE_KEY] = $en + $em;
} elseif ($em != 0) {
$ab[self::FILE_LIFE_KEY] = $en + $el->_options['file_life'];
}
$r = $el->_file($bv);
$ab = "\n" . " // mktime: " . $en . "\n" . " return " . var_export($ab, true) . "\n?>";
$cv = $el->_filePutContents($r, $ab);
return $cv;
}
static function load($bv)
{
$el =& self::getInstance();
$en = time();
if (!$el->test($bv)) {
return false;
}
$eo = $el->_file(self::CLEAR_ALL_KEY);
$r = $el->_file($bv);
if (is_file($eo) && filemtime($eo) > filemtime($r)) {
return false;
}
$ab = $el->_fileGetContents($r);
if (empty($ab[self::FILE_LIFE_KEY]) || $en < $ab[self::FILE_LIFE_KEY]) {
unset($ab[self::FILE_LIFE_KEY]);
return $ab;
}
return false;
}
protected function _filePutContents($r, $ep)
{
$el =& self::getInstance();
$eq = false;
$er = @fopen($r, 'ab+');
if ($er) {
if ($el->_options['file_locking']) {
@flock($er, LOCK_EX);
}
fseek($er, 0);
ftruncate($er, 0);
$es = @fwrite($er, $ep);
if (!($es === false)) {
$eq = true;
}
@fclose($er);
}
@chmod($r, $el->_options['cache_file_umask']);
return $eq;
}
protected function _file($bv)
{
$el =& self::getInstance();
$et = $el->_idToFileName($bv);
return $el->_options['cache_dir'] . $et;
}
protected function _idToFileName($bv)
{
$el =& self::getInstance();
$el->_id = $bv;
$x = $el->_options['file_name_prefix'];
$eq = $x . '---' . $bv;
return $eq;
}
static function test($bv)
{
$el =& self::getInstance();
$r = $el->_file($bv);
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
$el =& self::getInstance();
$el->save('CLEAR_ALL', self::CLEAR_ALL_KEY);
}
static function del($bv)
{
$el =& self::getInstance();
if (!$el->test($bv)) {
return false;
}
$r = $el->_file($bv);
return unlink($r);
}
}
class mdb
{
private static $_client;
private static $_db;
private static $_ins;
public static function obj($ct = 'myapp_dev')
{
if (!self::$_client) {
self::$_client = new \Sokil\Mongo\Client();
}
if (!self::$_db) {
self::$_db = self::$_client->{$ct};
}
return self::$_db;
}
public static function test()
{
$be = 1;
$eu = self::obj()->blogs;
$ev = $eu->find()->findAll();
$ab = object2array($ev);
$ew = 1;
foreach ($ab as $bx => $ex) {
unset($ex['_id']);
unset($ex['tid']);
unset($ex['tags']);
if (isset($ex['_intm'])) {
$ex['_intm'] = date('Y-m-d H:i:s', $ex['_intm']['sec']);
}
if (isset($ex['_uptm'])) {
$ex['_uptm'] = date('Y-m-d H:i:s', $ex['_uptm']['sec']);
}
$ex['uid'] = $be;
$v = db::save('blogs', $ex);
$ew++;
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
self::$_client = $cd = new Predis\Client(cfg::get_redis_cfg());
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
public static function init($ey = array())
{
$m = cfg::get('oauth', 'oauth')['uc'];
$m['host'] = env('UC_HOST', $m['host']);
$m['clientId'] = env('UC_CLIENT_ID', $m['clientId']);
$m['clientSecret'] = env('UC_CLIENT_SECRET', $m['clientSecret']);
$m['redirectUri'] = env('UC_REDIRECT_URI', $m['redirectUri']);
$m['username'] = env('UC_USERNAME', $m['username']);
$m['passwd'] = env('UC_PASSWD', $m['passwd']);
self::$oauth_cfg = $m;
if (isset($ey['host'])) {
self::$UC_HOST = $ey['host'];
}
}
public static function makeUrl($bs, $az = '')
{
if (!self::$oauth_cfg) {
self::init();
}
return self::$oauth_cfg['host'] . $bs . ($az ? '?' . $az : '');
}
public static function pwd_login($ez = null, $fg = null, $fh = null, $fi = null)
{
$fj = $ez ? $ez : self::$oauth_cfg['username'];
$cf = $fg ? $fg : self::$oauth_cfg['passwd'];
$fk = $fh ? $fh : self::$oauth_cfg['clientId'];
$fl = $fi ? $fi : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $fk, 'client_secret' => $fl, 'grant_type' => 'password', 'username' => $fj, 'password' => $cf];
$fm = self::makeUrl(self::API['accessToken']);
$cs = curl($fm, 10, 30, $ab);
$v = json_decode($cs, true);
self::_set_pwd_user($v);
return $v;
}
public static function get_admin_token($fn = array())
{
if (isset($fn['access_token'])) {
$bi = $fn['access_token'];
} else {
$v = self::pwd_login();
$bi = $v['data']['access_token'];
}
return $bi;
}
public static function id_login($bv, $fh = null, $fi = null, $cg = array())
{
$fk = $fh ? $fh : self::$oauth_cfg['clientId'];
$fl = $fi ? $fi : self::$oauth_cfg['clientSecret'];
$bi = self::get_admin_token($cg);
$ab = ['client_id' => $fk, 'client_secret' => $fl, 'grant_type' => 'id', 'access_token' => $bi, 'id' => $bv];
$fm = self::makeUrl(self::API['userAccessToken']);
$cs = curl($fm, 10, 30, $ab);
$v = json_decode($cs, true);
self::_set_id_user($v);
return $v;
}
public static function authurl($bk, $fo, $bi)
{
$fp = self::$oauth_cfg['host'] . "/api/sso/redirect?access_token={$bi}&app_id={$bk}&domain_id={$fo}";
return $fp;
}
public static function code_login($fq, $fr = null, $fh = null, $fi = null)
{
$fs = $fr ? $fr : self::$oauth_cfg['redirectUri'];
$fk = $fh ? $fh : self::$oauth_cfg['clientId'];
$fl = $fi ? $fi : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $fk, 'client_secret' => $fl, 'grant_type' => 'authorization_code', 'redirect_uri' => $fs, 'code' => $fq];
$fm = self::makeUrl(self::API['accessToken']);
$cs = curl($fm, 10, 30, $ab);
$v = json_decode($cs, true);
self::_set_code_user($v);
return $v;
}
public static function user_info($bi)
{
$fm = self::makeUrl(self::API['user'], 'access_token=' . $bi);
$cs = curl($fm);
$v = json_decode($cs, true);
self::_set_user_info($v);
return $v;
}
public static function reg_user($fj, $fg = '123456', $cg = array())
{
$bi = self::get_admin_token($cg);
$ab = ['username' => $fj, 'password' => $fg, 'access_token' => $bi];
$fm = self::makeUrl(self::API['user']);
$cs = curl($fm, 10, 30, $ab);
$ft = json_decode($cs, true);
return $ft;
}
public static function register_user($fj, $fg = '123456')
{
return self::reg_user($fj, $fg);
}
public static function find_user($fn = array())
{
$bi = self::get_admin_token($fn);
$az = 'access_token=' . $bi;
if (isset($fn['username'])) {
$az .= '&username=' . $fn['username'];
}
if (isset($fn['phone'])) {
$az .= '&phone=' . $fn['phone'];
}
$fm = self::makeUrl(self::API['finduser'], $az);
$cs = curl($fm, 10, 30);
$ft = json_decode($cs, true);
return $ft;
}
public static function edit_user($bi, $ab = array())
{
$fm = self::makeUrl(self::API['user']);
$ab['access_token'] = $bi;
$cd = new \GuzzleHttp\Client();
$cv = $cd->request('PUT', $fm, ['form_params' => $ab, 'headers' => ['X-Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded']]);
$cs = $cv->getBody();
return json_decode($cs, true);
}
public static function set_user_role($bi, $fo, $fu, $fv = 'guest')
{
$ab = ['access_token' => $bi, 'domain_id' => $fo, 'user_id' => $fu, 'role_name' => $fv];
$fm = self::makeUrl(self::API['userRole']);
$cs = curl($fm, 10, 30, $ab);
return json_decode($cs, true);
}
public static function user_role($bi, $fo)
{
$ab = ['access_token' => $bi, 'domain_id' => $fo];
$fm = self::makeUrl(self::API['userRole']);
$fm = "{$fm}?access_token={$bi}&domain_id={$fo}";
$cs = curl($fm, 10, 30);
$v = json_decode($cs, true);
self::_set_user_role($v);
return $v;
}
public static function has_role($fw)
{
if (self::$user_role && isset(self::$user_role['roles'])) {
$fx = self::$user_role['roles'];
foreach ($fx as $k => $fv) {
if ($fv['name'] == $fw) {
return true;
}
}
}
return false;
}
public static function create_domain($fy, $fz, $cg = array())
{
$bi = self::get_admin_token($cg);
$ab = ['access_token' => $bi, 'domain_name' => $fy, 'description' => $fz];
$fm = self::makeUrl(self::API['createDomain']);
$cs = curl($fm, 10, 30, $ab);
$v = json_decode($cs, true);
return $v;
}
public static function user_domain($bi)
{
$ab = ['access_token' => $bi];
$fm = self::makeUrl(self::API['userdomain']);
$fm = "{$fm}?access_token={$bi}";
$cs = curl($fm, 10, 30);
$v = json_decode($cs, true);
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
public static function test($bu, $ab)
{
}
public static function registration($ab)
{
$by = new Valitron\Validator($ab);
$gh = ['required' => [['name'], ['gender'], ['birthdate'], ['blood'], ['nationality'], ['country'], ['mobile'], ['emergency_contact_person'], ['emergency_mobile'], ['cloth_size']], 'length' => [['name', 4]], 'mobile' => [['mobile']]];
$by->rules($gh);
$by->labels(['name' => '名称', 'gender' => '性别', 'birthdate' => '生日']);
if ($by->validate()) {
return 0;
} else {
err($by->errors());
}
}
}
}
namespace mid {
use FastRoute\Dispatcher;
use FastRoute\RouteParser\Std as StdParser;
class TwigMid
{
public function __invoke($gi, $gj, $gk)
{
log_time("Twig Begin");
$gj = $gk($gi, $gj);
$gl = uripath($gi);
debug(">>>>>> TwigMid START : {$gl}  <<<<<<");
if ($gm = $this->getRoutePath($gi)) {
$br = \ctx::app()->getContainer()->view;
if (\ctx::isRetJson()) {
ret($br->data);
}
$gn = rtrim($gm, '/');
if ($gn == '/' || !$gn) {
$gn = 'index';
}
$bq = $gn;
$ab = [];
if (isset($br->data)) {
$ab = $br->data;
if (isset($br->data['tpl'])) {
$bq = $br->data['tpl'];
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
debug("<<<<<< TwigMid END : {$gl} >>>>>");
log_time("Twig End");
log_time("Twig Total", 'begin');
return $br->render($gj, tpl($bq), $ab);
} else {
return $gj;
}
}
public function getRoutePath($gi)
{
$go = \ctx::router()->dispatch($gi);
if ($go[0] === Dispatcher::FOUND) {
$aj = \ctx::router()->lookupRoute($go[1]);
$gp = $aj->getPattern();
$gq = new StdParser();
$gr = $gq->parse($gp);
foreach ($gr as $gs) {
foreach ($gs as $dz) {
if (is_string($dz)) {
return $dz;
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
public function __invoke($gi, $gj, $gk)
{
log_time("AuthMid Begin");
$gl = uripath($gi);
debug(">>>>>> AuthMid START : {$gl}  <<<<<<");
\ctx::init($gi);
$this->check_auth($gi, $gj);
debug("<<<<<< AuthMid END : {$gl} >>>>>");
log_time("AuthMid END");
$gj = $gk($gi, $gj);
return $gj;
}
public function isAjax($bs = '')
{
$gt = \cfg::get('route_type');
if ($gt) {
if ($gt['default'] == 'web') {
$this->isAjax = false;
}
if (isset($gt['web'])) {
}
if (isset($gt['api'])) {
}
}
if ($bs) {
if (startWith($bs, '/rest')) {
$this->isAjax = true;
}
}
return $this->isAjax;
}
public function check_auth($z, $bo)
{
list($gu, $ak, $gv) = $this->auth_cfg();
$gl = uripath($z);
$this->isAjax($gl);
if ($gl == '/') {
return true;
}
$gw = $this->check_list($gu, $gl);
if ($gw) {
$this->check_admin();
}
$gx = $this->check_list($ak, $gl);
if ($gx) {
$this->check_user();
}
$gy = $this->check_list($gv, $gl);
if (!$gy) {
$this->check_user();
}
info("check_auth: {$gl} admin:[{$gw}] user:[{$gx}] pub:[{$gy}]");
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
public function auth_error($gz = 1)
{
$hi = is_weixin();
$hj = isMobile();
$hk = \cfg::get('wechat_callback_domain');
info("auth_error: errorid: {$gz}, is_weixin: {$hi} , is_mobile: {$hj}");
$hl = $_SERVER['REQUEST_URI'];
if ($hi) {
header("Location: {$hk}/auth/wechat?_r={$hl}");
exit;
}
if ($hj) {
header("Location: {$hk}/auth/openwechat?_r={$hl}");
exit;
}
if ($this->isAjax()) {
ret($gz, 'auth error');
} else {
header('Location: /?_r=' . $hl);
exit;
}
}
public function auth_cfg()
{
$hm = \cfg::get('auth');
return [$hm['admin'], $hm['user'], $hm['public']];
}
public function check_list($ai, $gl)
{
foreach ($ai as $bs) {
if (startWith($gl, $bs)) {
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
public function __invoke($gi, $gj, $gk)
{
$this->init($gi, $gj, $gk);
log_time("{$this->classname} Begin");
$this->path_info = uripath($gi);
debug(">>>>>> {$this->name}Mid START : {$this->path_info}  <<<<<<");
$this->handelReq($gi, $gj);
debug("<<<<<< {$this->name}Mid END : {$this->path_info} >>>>>");
log_time("{$this->classname} End");
$gj = $gk($gi, $gj);
return $gj;
}
public function handelReq($z, $bo)
{
$bs = \cfg::get($this->classname, 'mid.yml');
if (is_array($bs)) {
$this->handlePathArray($bs, $z, $bo);
} else {
if (startWith($this->path_info, $bs)) {
$this->handlePath($z, $bo);
}
}
}
public function handlePathArray($hn, $z, $bo)
{
foreach ($hn as $bs => $ho) {
if (startWith($this->path_info, $bs)) {
debug("{$this->path_info} match {$bs} {$ho}");
$this->{$ho}($z, $bo);
break;
}
}
}
public function handlePath($z, $bo)
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
public function __invoke($gi, $gj, $gk)
{
log_time("RestMid Begin");
$this->path_info = uripath($gi);
$this->rest_prefix = \cfg::get_rest_prefix();
debug(">>>>>> RestMid START : {$this->path_info}  <<<<<<");
if ($this->isRest($gi)) {
info("====== RestMid Handle REST: {$this->path_info}  =======");
if ($this->isApiDoc($gi)) {
$this->apiDoc($gi);
} else {
$this->handelRest($gi);
}
} else {
debug("====== RestMid PASS REST: {$this->path_info}  =======");
}
debug("<<<<<< RestMid END : {$this->path_info}  >>>>>");
log_time("RestMid Begin");
$gj = $gk($gi, $gj);
return $gj;
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
$bs = str_replace($this->rest_prefix, '', $this->path_info);
$bt = explode('/', $bs);
$bu = getArg($bt, 1, '');
$bv = getArg($bt, 2, '');
$ho = $z->getMethod();
info(" method: {$ho}, name: {$bu}, id: {$bv}");
$hp = "handle{$ho}";
$this->{$hp}($z, $bu, $bv);
}
public function handleGET($z, $bu, $bv)
{
if ($bv) {
rest::renderItem($bu, $bv);
} else {
rest::renderList($bu);
}
}
public function handlePOST($z, $bu, $bv)
{
self::beforeData($bu, 'post');
rest::renderPostData($bu);
}
public function handlePUT($z, $bu, $bv)
{
self::beforeData($bu, 'put');
rest::renderPutData($bu, $bv);
}
public function handleDELETE($z, $bu, $bv)
{
rest::delete($z, $bu, $bv);
}
public function handleOPTIONS($z, $bu, $bv)
{
sendJson([]);
}
public function beforeData($bu, $c)
{
$hq = \cfg::get('rest_maps', 'rest.yml');
if (isset($hq[$bu])) {
$m = $hq[$bu][$c];
if ($m) {
$hr = $m['xmap'];
if ($hr) {
$ab = \ctx::data();
foreach ($hr as $bx => $by) {
unset($ab[$by]);
}
\ctx::data($ab);
}
}
}
}
public function apiDoc($z)
{
$hs = rd::genApi();
echo $hs;
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
public static function whereStr($dm, $bu)
{
$v = '';
foreach ($dm as $bx => $by) {
$gp = '/(.*)\\{(.*)\\}/i';
$bw = preg_match($gp, $bx, $ht);
$hu = '=';
if ($ht) {
$hv = $ht[1];
$hu = $ht[2];
} else {
$hv = $bx;
}
if ($hw = db::valid_table_col($bu, $hv)) {
if ($hw == 2) {
if ($hu == 'in') {
if (is_array($by)) {
$by = implode("','", $by);
}
$v .= " and t1.{$hv} {$hu} ('{$by}')";
} else {
$v .= " and t1.{$hv}{$hu}'{$by}'";
}
} else {
if ($hu == 'in') {
if (is_array($by)) {
$by = implode(',', $by);
}
$v .= " and t1.{$hv} {$hu} ({$by})";
} else {
$v .= " and t1.{$hv}{$hu}{$by}";
}
}
} else {
}
info("[{$bu}] [{$hv}] [{$hw}] {$v}");
}
return $v;
}
public static function getSqlFrom($bu, $hx, $be, $hy, $hz, $cg = array())
{
$ij = isset($_GET['tags']) ? 1 : isset($cg['tags']) ? 1 : 0;
$ik = isset($_GET['isar']) ? 1 : 0;
$il = RestHelper::get_rest_xwh_tags_list();
if ($il && in_array($bu, $il)) {
$ij = 0;
}
$im = isset($cg['force_ar']) || RestHelper::isAdmin() && $ik ? "1=1" : "t1.uid={$be}";
if ($ij) {
$in = isset($_GET['tags']) ? get('tags') : $cg['tags'];
if ($in && is_array($in) && count($in) == 1 && !$in[0]) {
$in = '';
}
$io = '';
$ip = 'not in';
if ($in) {
if (is_string($in)) {
$in = [$in];
}
$iq = implode("','", $in);
$io = "and `name` in ('{$iq}')";
$ip = 'in';
$ir = " from {$bu} t1\n                               join tag_items t on t1.id=t.`oid`\n                               {$hx}\n                               where {$im} and t._st=1  and t.tagid {$ip}\n                               (select id from tags where type='{$bu}' {$io} )\n                               {$hz}";
} else {
$ir = " from {$bu} t1\n                              {$hx}\n                              where {$im} and t1.id not in\n                              (select oid from tag_items where type='{$bu}')\n                              {$hz}";
}
} else {
$is = $im;
if (RestHelper::isAdmin()) {
if ($bu == RestHelper::user_tbl()) {
$is = "t1.id={$be}";
}
}
$ir = "from {$bu} t1 {$hx} where {$is} {$hy} {$hz}";
}
return $ir;
}
public static function getSql($bu, $cg = array())
{
$be = RestHelper::uid();
$it = RestHelper::get('sort', '_intm');
$iu = RestHelper::get('asc', -1);
if (!db::valid_table_col($bu, $it)) {
$it = '_intm';
}
$iu = $iu > 0 ? 'asc' : 'desc';
$hz = " order by t1.{$it} {$iu}";
$iv = RestHelper::gets();
$iv = un_select_keys(['sort', 'asc'], $iv);
$iw = RestHelper::get('_st', 1);
$dm = dissoc($iv, ['token', '_st']);
if ($iw != 'all') {
$dm['_st'] = $iw;
}
$hy = self::whereStr($dm, $bu);
$ix = RestHelper::get('search', '');
$iy = RestHelper::get('search-key', '');
if ($ix && $iy) {
$hy .= " and {$iy} like '%{$ix}%'";
}
$iz = RestHelper::select_add();
$hx = RestHelper::join_add();
$jk = RestHelper::get('fields', []);
$jl = 't1.*';
if ($jk) {
$jl = '';
foreach ($jk as $jm) {
if ($jl) {
$jl .= ',';
}
$jl .= 't1.' . $jm;
}
}
$ir = self::getSqlFrom($bu, $hx, $be, $hy, $hz, $cg);
$cz = "select {$jl} {$iz} {$ir}";
$jn = "select count(*) cnt {$ir}";
$ag = RestHelper::offset();
$af = RestHelper::pagesize();
$cz .= " limit {$ag},{$af}";
return [$cz, $jn];
}
public static function getResName($bu, $cg)
{
$jo = getArg($cg, 'res_name', '');
if ($jo) {
return $jo;
}
$jp = RestHelper::get('res_id_key', '');
if ($jp) {
$jq = RestHelper::get($jp);
$bu .= '_' . $jq;
}
return $bu;
}
public static function getList($bu, $cg = array())
{
$be = RestHelper::uid();
list($cz, $jn) = self::getSql($bu, $cg);
info($cz);
$dj = db::query($cz);
$ap = (int) db::queryOne($jn);
$jr = RestHelper::get_rest_join_tags_list();
if ($jr && in_array($bu, $jr)) {
$js = getKeyValues($dj, 'id');
$in = RestHelper::get_tags_by_oid($be, $js, $bu);
info("get tags ok: {$be} {$bu} " . json_encode($js));
foreach ($dj as $bx => $du) {
if (isset($in[$du['id']])) {
$jt = $in[$du['id']];
$dj[$bx]['tags'] = getKeyValues($jt, 'name');
}
}
info('set tags ok');
}
if (isset($cg['join_cols'])) {
foreach ($cg['join_cols'] as $ju => $jv) {
$jw = getArg($jv, 'jtype', '1-1');
$jx = getArg($jv, 'jkeys', []);
$jy = getArg($jv, 'jwhe', []);
$jz = getArg($jv, 'ast', ['id' => 'ASC']);
if (is_string($jv['on'])) {
$kl = 'id';
$km = $jv['on'];
} else {
if (is_array($jv['on'])) {
$kn = array_keys($jv['on']);
$kl = $kn[0];
$km = $jv['on'][$kl];
}
}
$js = getKeyValues($dj, $kl);
$jy[$km] = $js;
$ko = \db::all($ju, ['AND' => $jy, 'ORDER' => $jz]);
foreach ($ko as $k => $kp) {
foreach ($dj as $bx => &$du) {
if (isset($du[$kl]) && isset($kp[$km]) && $du[$kl] == $kp[$km]) {
if ($jw == '1-1') {
foreach ($jx as $kq => $kr) {
$du[$kr] = $kp[$kq];
}
}
$kq = isset($jv['jkey']) ? $jv['jkey'] : $ju;
if ($jw == '1-n') {
$du[$kq][] = $kp[$kq];
}
if ($jw == '1-n-o') {
$du[$kq][] = $kp;
}
if ($jw == '1-1-o') {
$du[$kq] = $kp;
}
}
}
}
}
}
$jo = self::getResName($bu, $cg);
\ctx::count($ap);
$ks = ['pageinfo' => \ctx::pageinfo()];
return ['data' => $dj, 'res-name' => $jo, 'count' => $ap, 'meta' => $ks];
}
public static function renderList($bu)
{
ret(self::getList($bu));
}
public static function getItem($bu, $bv)
{
$be = RestHelper::uid();
info("---GET---: {$bu}/{$bv}");
$jo = "{$bu}-{$bv}";
if ($bu == 'colls') {
$dz = db::row($bu, ["{$bu}.id" => $bv], ["{$bu}.id", "{$bu}.title", "{$bu}.from_url", "{$bu}._intm", "{$bu}._uptm", "posts.content"], ['[>]posts' => ['uuid' => 'uuid']]);
} else {
if ($bu == 'feeds') {
$c = RestHelper::get('type');
$kt = RestHelper::get('rid');
$dz = db::row($bu, ['AND' => ['uid' => $be, 'rid' => $bv, 'type' => $c]]);
if (!$dz) {
$dz = ['rid' => $bv, 'type' => $c, 'excerpt' => '', 'title' => ''];
}
$jo = "{$jo}-{$c}-{$bv}";
} else {
$dz = db::row($bu, ['id' => $bv]);
}
}
if ($ku = RestHelper::rest_extra_data()) {
$dz = array_merge($dz, $ku);
}
return ['data' => $dz, 'res-name' => $jo, 'count' => 1];
}
public static function renderItem($bu, $bv)
{
ret(self::getItem($bu, $bv));
}
public static function postData($bu)
{
$ab = db::tbl_data($bu, RestHelper::data());
$be = RestHelper::uid();
$in = [];
if ($bu == 'tags') {
$in = RestHelper::get_tag_by_name($be, $ab['name'], $ab['type']);
}
if ($in && $bu == 'tags') {
$ab = $in[0];
} else {
info("---POST---: {$bu} " . json_encode($ab));
unset($ab['token']);
$ab['_intm'] = date('Y-m-d H:i:s');
if (!isset($ab['uid'])) {
$ab['uid'] = $be;
}
$ab = db::tbl_data($bu, $ab);
$ab = db::save($bu, $ab);
}
return $ab;
}
public static function renderPostData($bu)
{
$ab = self::postData($bu);
ret($ab);
}
public static function putData($bu, $bv)
{
if ($bv == 0 || $bv == '' || trim($bv) == '') {
info(" PUT ID IS EMPTY !!!");
ret();
}
$be = RestHelper::uid();
$ab = RestHelper::data();
unset($ab['token']);
unset($ab['uniqid']);
self::checkOwner($bu, $bv, $be);
if (isset($ab['inc'])) {
$jm = $ab['inc'];
unset($ab['inc']);
db::exec("UPDATE {$bu} SET {$jm} = {$jm} + 1 WHERE id={$bv}");
}
if (isset($ab['dec'])) {
$jm = $ab['dec'];
unset($ab['dec']);
db::exec("UPDATE {$bu} SET {$jm} = {$jm} - 1 WHERE id={$bv}");
}
if (isset($ab['tags'])) {
RestHelper::del_tag_by_name($be, $bv, $bu);
$in = $ab['tags'];
foreach ($in as $kv) {
$kw = RestHelper::get_tag_by_name($be, $kv, $bu);
if ($kw) {
$kx = $kw[0]['id'];
RestHelper::save_tag_items($be, $kx, $bv, $bu);
}
}
}
info("---PUT---: {$bu}/{$bv} " . json_encode($ab));
$ab = db::tbl_data($bu, $ab);
$ab['id'] = $bv;
db::save($bu, $ab);
return $ab;
}
public static function renderPutData($bu, $bv)
{
$ab = self::putData($bu, $bv);
ret($ab);
}
public static function delete($z, $bu, $bv)
{
$be = RestHelper::uid();
self::checkOwner($bu, $bv, $be);
db::save($bu, ['_st' => 0, 'id' => $bv]);
ret([]);
}
public static function checkOwner($bu, $bv, $be)
{
$dm = ['AND' => ['id' => $bv], 'LIMIT' => 1];
$dj = db::obj()->select($bu, '*', $dm);
if ($dj) {
$dz = $dj[0];
} else {
$dz = null;
}
if ($dz) {
if (array_key_exists('uid', $dz)) {
$ky = $dz['uid'];
if ($bu == RestHelper::user_tbl()) {
$ky = $dz['id'];
}
if ($ky != $be && (!RestHelper::isAdmin() || !RestHelper::isAdminRest())) {
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
public static function ins($kz = null)
{
if ($kz) {
self::$_ins = $kz;
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
public static function get_tags_by_oid($be, $js, $bu)
{
return self::ins()->get_tags_by_oid($be, $js, $bu);
}
public static function get_tag_by_name($be, $bu, $c)
{
return self::ins()->get_tag_by_name($be, $bu, $c);
}
public static function del_tag_by_name($be, $bv, $bu)
{
return self::ins()->del_tag_by_name($be, $bv, $bu);
}
public static function save_tag_items($be, $kx, $bv, $bu)
{
return self::ins()->save_tag_items($be, $kx, $bv, $bu);
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
public static function get($bx, $lm = '')
{
return self::ins()->get($bx, $lm);
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
public function get_tags_by_oid($be, $js, $bu);
public function get_tag_by_name($be, $bu, $c);
public function del_tag_by_name($be, $bv, $bu);
public function save_tag_items($be, $kx, $bv, $bu);
public function isAdmin();
public function isAdminRest();
public function user_tbl();
public function data();
public function uid();
public function get($bx, $lm);
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
public function get_tags_by_oid($be, $js, $bu)
{
return tag::getTagsByOids($be, $js, $bu);
}
public function get_tag_by_name($be, $bu, $c)
{
return tag::getTagByName($be, $bu, $c);
}
public function del_tag_by_name($be, $bv, $bu)
{
return tag::delTagByOid($be, $bv, $bu);
}
public function save_tag_items($be, $kx, $bv, $bu)
{
return tag::saveTagItems($be, $kx, $bv, $bu);
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
public function get($bx, $lm)
{
return get($bx, $lm);
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
public static function getTagByName($be, $kv, $c)
{
$in = \db::all(self::$tbl_name, ['AND' => ['uid' => $be, 'name' => $kv, 'type' => $c, '_st' => 1]]);
return $in;
}
public static function delTagByOid($be, $ln, $lo)
{
info("del tag: {$be}, {$ln}, {$lo}");
$v = \db::update(self::$tbl_items_name, ['_st' => 0], ['AND' => ['uid' => $be, 'oid' => $ln, 'type' => $lo]]);
info($v);
}
public static function saveTagItems($be, $lp, $ln, $lo)
{
\db::save('tag_items', ['tagid' => $lp, 'uid' => $be, 'oid' => $ln, 'type' => $lo, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')]);
}
public static function getTagsByType($be, $c)
{
$in = \db::all(self::$tbl_name, ['AND' => ['uid' => $be, 'type' => $c, '_st' => 1]]);
return $in;
}
public static function getTagsByOid($be, $ln, $c)
{
$cz = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid={$ln} and t2.type='{$c}' and t2._st=1";
$dj = \db::query($cz);
return getKeyValues($dj, 'name');
}
public static function getTagsByOids($be, $lq, $c)
{
if (is_array($lq)) {
$lq = implode(',', $lq);
}
$cz = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid in ({$lq}) and t2.type='{$c}' and t2._st=1";
$dj = \db::query($cz);
$ab = groupArray($dj, 'oid');
return $ab;
}
public static function countByTag($be, $kv, $c)
{
$cz = "select count(*) cnt, t1.id id from tags t1 join tag_items t2 on t1.id = t2.tagid where t1.name='{$kv}' and t1.type='{$c}' and t1.uid={$be}";
$dj = \db::query($cz);
return [$dj[0]['cnt'], $dj[0]['id']];
}
public static function saveTag($be, $kv, $c)
{
$ab = ['uid' => $be, 'name' => $kv, 'type' => $c, 'count' => 1, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
$ab = \db::save('tags', $ab);
return $ab;
}
public static function countTags($be, $lr, $bu)
{
foreach ($lr as $kv) {
list($ls, $bv) = self::countByTag($be, $kv, $bu);
echo "{$kv} {$ls} {$bv} <br>";
\db::update('tags', ['count' => $ls], ['id' => $bv]);
}
}
public static function saveRepoTags($be, $lt)
{
$bu = 'stars';
echo count($lt) . "<br>";
$lr = [];
foreach ($lt as $lu) {
$lv = $lu['repoId'];
$in = isset($lu['tags']) ? $lu['tags'] : [];
if ($in) {
foreach ($in as $kv) {
if (!in_array($kv, $lr)) {
$lr[] = $kv;
}
$in = self::getTagByName($be, $kv, $bu);
if (!$in) {
$kw = self::saveTag($be, $kv, $bu);
} else {
$kw = $in[0];
}
$lp = $kw['id'];
$lw = getStarByRepoId($be, $lv);
if ($lw) {
$ln = $lw[0]['id'];
$lx = self::getTagsByOid($be, $ln, $bu);
if ($kw && !in_array($kv, $lx)) {
self::saveTagItems($be, $lp, $ln, $bu);
}
} else {
echo "-------- star for {$lv} not found <br>";
}
}
} else {
}
}
self::countTags($be, $lr, $bu);
}
public static function getTagItem($ly, $be, $lz, $dx, $mn)
{
$cz = "select * from {$lz} where {$dx}={$mn} and uid={$be}";
return $ly->query($cz)->fetchAll();
}
public static function saveItemTags($ly, $be, $bu, $mo, $dx = 'id')
{
echo count($mo) . "<br>";
$lr = [];
foreach ($mo as $mp) {
$mn = $mp[$dx];
$in = isset($mp['tags']) ? $mp['tags'] : [];
if ($in) {
foreach ($in as $kv) {
if (!in_array($kv, $lr)) {
$lr[] = $kv;
}
$in = getTagByName($ly, $be, $kv, $bu);
if (!$in) {
$kw = saveTag($ly, $be, $kv, $bu);
} else {
$kw = $in[0];
}
$lp = $kw['id'];
$lw = getTagItem($ly, $be, $bu, $dx, $mn);
if ($lw) {
$ln = $lw[0]['id'];
$lx = getTagsByOid($ly, $be, $ln, $bu);
if ($kw && !in_array($kv, $lx)) {
saveTagItems($ly, $be, $lp, $ln, $bu);
}
} else {
echo "-------- star for {$mn} not found <br>";
}
}
} else {
}
}
countTags($ly, $be, $lr, $bu);
}
}
}
namespace core {
class Auth
{
public static function login($app, $ce = 'login', $cf = 'passwd')
{
$bd = \cfg::get('user_tbl_name');
$bg = \cfg::get('use_ucenter_oauth');
$aw = cguid();
$mq = null;
$ay = null;
$ab = \ctx::data();
$bh = $ab['auth_type'];
debug("auth type: {$bh}");
if ($bh) {
if ($bh == 'cube') {
info("cube auth ...");
$aw .= '$$cube';
$ak = cube_user_login($app, $ce, $cf);
if ($ak) {
$ak['luser'] = local_user('cube_uid', $ak['user']['id'], $bd);
cache_user($aw, $ak);
$ay = $ak['user'];
}
}
} else {
if ($bg) {
list($bi, $ay, $mr) = uc_user_login($app, $ce, $cf);
$ak = $ay;
$mq = ['access_token' => $bi, 'userinfo' => $ay, 'role_list' => $mr, 'luser' => local_user('uc_id', $ay['user_id'], $bd)];
extract(cache_user($aw, $mq));
$ay = select_keys(['username', 'phone', 'roles', 'email'], $ay);
} else {
$ak = user_login($app, $ce, $cf, $bd, 1);
if ($ak) {
$ak['username'] = $ak[$ce];
$mq = ['user' => $ak];
extract(cache_user($aw, $mq));
$ay = select_keys([$ce], $ak);
}
}
}
if ($ak) {
ret(['token' => $aw, 'userinfo' => $ay]);
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
public function __construct($ms = array())
{
$this->items = $this->getArrayItems($ms);
}
public function add($dw, $l = null)
{
if (is_array($dw)) {
foreach ($dw as $k => $l) {
$this->add($k, $l);
}
} elseif (is_null($this->get($dw))) {
$this->set($dw, $l);
}
}
public function all()
{
return $this->items;
}
public function clear($dw = null)
{
if (is_null($dw)) {
$this->items = [];
return;
}
$dw = (array) $dw;
foreach ($dw as $k) {
$this->set($k, []);
}
}
public function delete($dw)
{
$dw = (array) $dw;
foreach ($dw as $k) {
if ($this->exists($this->items, $k)) {
unset($this->items[$k]);
continue;
}
$ms =& $this->items;
$mt = explode('.', $k);
$mu = array_pop($mt);
foreach ($mt as $mv) {
if (!isset($ms[$mv]) || !is_array($ms[$mv])) {
continue 2;
}
$ms =& $ms[$mv];
}
unset($ms[$mu]);
}
}
protected function exists($mw, $k)
{
return array_key_exists($k, $mw);
}
public function get($k = null, $mx = null)
{
if (is_null($k)) {
return $this->items;
}
if ($this->exists($this->items, $k)) {
return $this->items[$k];
}
if (strpos($k, '.') === false) {
return $mx;
}
$ms = $this->items;
foreach (explode('.', $k) as $mv) {
if (!is_array($ms) || !$this->exists($ms, $mv)) {
return $mx;
}
$ms =& $ms[$mv];
}
return $ms;
}
protected function getArrayItems($ms)
{
if (is_array($ms)) {
return $ms;
} elseif ($ms instanceof self) {
return $ms->all();
}
return (array) $ms;
}
public function has($dw)
{
$dw = (array) $dw;
if (!$this->items || $dw === []) {
return false;
}
foreach ($dw as $k) {
$ms = $this->items;
if ($this->exists($ms, $k)) {
continue;
}
foreach (explode('.', $k) as $mv) {
if (!is_array($ms) || !$this->exists($ms, $mv)) {
return false;
}
$ms = $ms[$mv];
}
}
return true;
}
public function isEmpty($dw = null)
{
if (is_null($dw)) {
return empty($this->items);
}
$dw = (array) $dw;
foreach ($dw as $k) {
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
$ms = (array) $this->get($k);
$l = array_merge($ms, $this->getArrayItems($l));
$this->set($k, $l);
} elseif ($k instanceof self) {
$this->items = array_merge($this->items, $k->all());
}
}
public function pull($k = null, $mx = null)
{
if (is_null($k)) {
$l = $this->all();
$this->clear();
return $l;
}
$l = $this->get($k, $mx);
$this->delete($k);
return $l;
}
public function push($k, $l = null)
{
if (is_null($l)) {
$this->items[] = $k;
return;
}
$ms = $this->get($k);
if (is_array($ms) || is_null($ms)) {
$ms[] = $l;
$this->set($k, $ms);
}
}
public function set($dw, $l = null)
{
if (is_array($dw)) {
foreach ($dw as $k => $l) {
$this->set($k, $l);
}
return;
}
$ms =& $this->items;
foreach (explode('.', $dw) as $k) {
if (!isset($ms[$k]) || !is_array($ms[$k])) {
$ms[$k] = [];
}
$ms =& $ms[$k];
}
$ms = $l;
}
public function setArray($ms)
{
$this->items = $this->getArrayItems($ms);
}
public function setReference(array &$ms)
{
$this->items =& $ms;
}
public function toJson($k = null, $ek = 0)
{
if (is_string($k)) {
return json_encode($this->get($k), $ek);
}
$ek = $k === null ? 0 : $k;
return json_encode($this->items, $ek);
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
public function __construct($my = '')
{
if ($my) {
$this->service = $my;
$cg = self::$_services[$this->service];
$mz = $cg['url'];
debug("init client: {$mz}");
$this->client = new Client(['base_uri' => $mz, 'timeout' => 12.0]);
}
}
public static function add($cg = array())
{
if ($cg) {
$bu = $cg['name'];
if (!isset(self::$_services[$bu])) {
self::$_services[$bu] = $cg;
}
}
}
public static function init()
{
$no = \cfg::get('service_list', 'service');
if ($no) {
foreach ($no as $m) {
self::add($m);
}
}
}
public function getRest($my, $x = '/rest')
{
return $this->getService($my, $x . '/');
}
public function getService($my, $x = '')
{
if (isset(self::$_services[$my])) {
if (!isset(self::$_ins[$my])) {
self::$_ins[$my] = new Service($my);
}
}
if (isset(self::$_ins[$my])) {
$kz = self::$_ins[$my];
if ($x) {
$kz->setPrefix($x);
}
return $kz;
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
$bw = $this->body();
$ab = json_decode($bw, true);
return $ab;
}
public function body()
{
if ($this->resp) {
return $this->resp->getBody();
}
}
public function __call($ho, $np)
{
$cg = self::$_services[$this->service];
$mz = $cg['url'];
$bk = $cg['appid'];
$bf = $cg['appkey'];
$nq = getArg($np, 0, []);
$ab = getArg($nq, 'data', []);
$ab = array_merge($ab, $_GET);
unset($nr['token']);
$ab['appid'] = $bk;
$ab['date'] = date("Y-m-d H:i:s");
$ab['sign'] = gen_sign($ab, $bf);
$ns = getArg($nq, 'path', '');
$nt = getArg($nq, 'suffix', '');
$ns = $this->prefix . $ns . $nt;
$ho = strtoupper($ho);
debug("api_url: {$bk} {$bf} {$mz}");
debug("api_name: {$ns} [{$ho}]");
debug("data: " . json_encode($ab));
try {
if (in_array($ho, ['GET'])) {
$nu = $nv == 'GET' ? 'query' : 'form_params';
$this->resp = $this->client->request($ho, $ns, [$nu => $ab]);
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
public function __get($nw)
{
$ho = 'get' . ucfirst($nw);
if (method_exists($this, $ho)) {
$nx = new ReflectionMethod($this, $ho);
if (!$nx->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $nw)) {
return $this->{$nw};
}
}
public function __set($nw, $l)
{
$ho = 'set' . ucfirst($nw);
if (method_exists($this, $ho)) {
$nx = new ReflectionMethod($this, $ho);
if (!$nx->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $nw)) {
$this->{$nw} = $l;
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
function __construct($ab, $cg = array())
{
$this->stack = $ab;
if (isset($cg['pid_key'])) {
$this->pid_key = $cg['pid_key'];
}
if (isset($cg['name_key'])) {
$this->name_key = $cg['name_key'];
}
if (isset($cg['children_key'])) {
$this->children_key = $cg['children_key'];
}
if (isset($cg['ext_keys'])) {
$this->ext_keys = $cg['ext_keys'];
}
if (isset($cg['pnid'])) {
$this->pick_node_id = $cg['pnid'];
}
$ny = 100;
while (count($this->stack) && $ny > 0) {
$ny -= 1;
debug("count stack: " . count($this->stack));
$this->branchify(array_shift($this->stack));
}
}
protected function branchify(&$nz)
{
if ($this->pick_node_id) {
if ($nz['id'] == $this->pick_node_id) {
$this->addLeaf($this->tree, $nz);
return;
}
} else {
if (null === $nz[$this->pid_key] || 0 == $nz[$this->pid_key]) {
$this->addLeaf($this->tree, $nz);
return;
}
}
if (isset($this->leafIndex[$nz[$this->pid_key]])) {
$this->addLeaf($this->leafIndex[$nz[$this->pid_key]][$this->children_key], $nz);
} else {
debug("back to stack: " . json_encode($nz) . json_encode($this->leafIndex));
$this->stack[] = $nz;
}
}
protected function addLeaf(&$op, $nz)
{
$oq = array('id' => $nz['id'], $this->name_key => $nz['name'], 'data' => $nz, $this->children_key => array());
foreach ($this->ext_keys as $bx => $by) {
if (isset($nz[$bx])) {
$oq[$by] = $nz[$bx];
}
}
$op[] = $oq;
$this->leafIndex[$nz['id']] =& $op[count($op) - 1];
}
protected function addChild($op, $nz)
{
$this->leafIndex[$nz['id']] &= $op[$this->children_key][] = $nz;
}
public function getTree()
{
return $this->tree;
}
}
}
namespace {
if (getenv('WHOOPS_ENABLED') == 'yes') {
$or = new \Whoops\Run();
$or->pushHandler(new \Whoops\Handler\PrettyPageHandler());
$or->register();
}
function getCaller($os = NULL)
{
$ot = debug_backtrace();
$ou = $ot[2];
if (isset($os)) {
return $ou[$os];
} else {
return $ou;
}
}
function getCallerStr($ov = 4)
{
$ot = debug_backtrace();
$ou = $ot[2];
$ow = $ot[1];
$ox = $ou['function'];
$oy = isset($ou['class']) ? $ou['class'] : '';
$oz = $ow['file'];
$pq = $ow['line'];
if ($ov == 4) {
$bw = "{$oy} {$ox} {$oz} {$pq}";
} elseif ($ov == 3) {
$bw = "{$oy} {$ox} {$pq}";
} else {
$bw = "{$oy} {$pq}";
}
return $bw;
}
function wlog($bs, $pr, $ps)
{
if (is_dir($bs)) {
$pt = date('Y-m-d', time());
$ps .= "\n";
file_put_contents($bs . "/{$pr}-{$pt}.log", $ps, FILE_APPEND);
}
}
function folder_exist($pu)
{
$bs = realpath($pu);
return ($bs !== false and is_dir($bs)) ? $bs : false;
}
use Rosio\EncryptedCookie\CryptoSystem\AES_SHA;
function encrypt($ab, $pv)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$pw = $m['symmetric_key'];
$px = $m['hmac_key'];
$py = new AES_SHA($pw, $px);
return $py->encrypt(serialize($ab), $pv);
}
function decrypt($ab)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$pw = $m['symmetric_key'];
$px = $m['hmac_key'];
$py = new AES_SHA($pw, $px);
return unserialize($py->decrypt($ab));
}
function encrypt_cookie($pz)
{
return encrypt($pz->getData(), $pz->getExpiration());
}
function ecode($ep, $k)
{
$k = substr(openssl_digest(openssl_digest($k, 'sha1', true), 'sha1', true), 0, 16);
$ab = openssl_encrypt($ep, 'AES-128-ECB', $k, OPENSSL_RAW_DATA);
$ab = strtoupper(bin2hex($ab));
return $ab;
}
function dcode($ep, $k)
{
$k = substr(openssl_digest(openssl_digest($k, 'sha1', true), 'sha1', true), 0, 16);
$qr = openssl_decrypt(hex2bin($ep), 'AES-128-ECB', $k, OPENSSL_RAW_DATA);
return $qr;
}
define('UC_KEY', 'iHuiPaoiwoeurqoejjdfklasdjfqowiefiqwjflkjdfsdfa');
function _authcode($ep, $qs = 'DECODE', $k = '', $qt = 0)
{
$qu = 4;
$k = md5($k ? $k : UC_KEY);
$qv = md5(substr($k, 0, 16));
$qw = md5(substr($k, 16, 16));
$qx = $qu ? $qs == 'DECODE' ? substr($ep, 0, $qu) : substr(md5(microtime()), -$qu) : '';
$qy = $qv . md5($qv . $qx);
$qz = strlen($qy);
$ep = $qs == 'DECODE' ? base64_decode(substr($ep, $qu)) : sprintf('%010d', $qt ? $qt + time() : 0) . substr(md5($ep . $qw), 0, 16) . $ep;
$rs = strlen($ep);
$eq = '';
$rt = range(0, 255);
$ru = array();
for ($ew = 0; $ew <= 255; $ew++) {
$ru[$ew] = ord($qy[$ew % $qz]);
}
for ($rv = $ew = 0; $ew < 256; $ew++) {
$rv = ($rv + $rt[$ew] + $ru[$ew]) % 256;
$es = $rt[$ew];
$rt[$ew] = $rt[$rv];
$rt[$rv] = $es;
}
for ($rw = $rv = $ew = 0; $ew < $rs; $ew++) {
$rw = ($rw + 1) % 256;
$rv = ($rv + $rt[$rw]) % 256;
$es = $rt[$rw];
$rt[$rw] = $rt[$rv];
$rt[$rv] = $es;
$eq .= chr(ord($ep[$ew]) ^ $rt[($rt[$rw] + $rt[$rv]) % 256]);
}
if ($qs == 'DECODE') {
if ((substr($eq, 0, 10) == 0 || substr($eq, 0, 10) - time() > 0) && substr($eq, 10, 16) == substr(md5(substr($eq, 26) . $qw), 0, 16)) {
return substr($eq, 26);
} else {
return '';
}
} else {
return $qx . str_replace('=', '', base64_encode($eq));
}
}
function object2array(&$rx)
{
$rx = json_decode(json_encode($rx), true);
return $rx;
}
function getKeyValues($ab, $k, $de = null)
{
if (!$de) {
$de = function ($by) {
return $by;
};
}
$bc = array();
if ($ab && is_array($ab)) {
foreach ($ab as $dz) {
if (isset($dz[$k]) && $dz[$k]) {
$u = $dz[$k];
if ($de) {
$u = $de($u);
}
$bc[] = $u;
}
}
}
return array_unique($bc);
}
if (!function_exists('indexArray')) {
function indexArray($ab, $k, $fn = null)
{
$bc = array();
if ($ab && is_array($ab)) {
foreach ($ab as $dz) {
if (!isset($dz[$k]) || !$dz[$k] || !is_scalar($dz[$k])) {
continue;
}
if (!$fn) {
$bc[$dz[$k]] = $dz;
} else {
if (is_string($fn)) {
$bc[$dz[$k]] = $dz[$fn];
} else {
if (is_array($fn)) {
$ry = [];
foreach ($fn as $bx => $by) {
$ry[$by] = $dz[$by];
}
$bc[$dz[$k]] = $dz[$fn];
}
}
}
}
}
return $bc;
}
}
if (!function_exists('groupArray')) {
function groupArray($mw, $k)
{
if (!is_array($mw) || !$mw) {
return array();
}
$ab = array();
foreach ($mw as $dz) {
if (isset($dz[$k]) && $dz[$k]) {
$ab[$dz[$k]][] = $dz;
}
}
return $ab;
}
}
function select_keys($dw, $ab)
{
$v = [];
foreach ($dw as $k) {
if (isset($ab[$k])) {
$v[$k] = $ab[$k];
} else {
$v[$k] = '';
}
}
return $v;
}
function un_select_keys($dw, $ab)
{
$v = [];
foreach ($ab as $bx => $dz) {
if (!in_array($bx, $dw)) {
$v[$bx] = $dz;
}
}
return $v;
}
function copyKey($ab, $rz, $st)
{
foreach ($ab as &$dz) {
$dz[$st] = $dz[$rz];
}
return $ab;
}
function addKey($ab, $k, $u)
{
foreach ($ab as &$dz) {
$dz[$k] = $u;
}
return $ab;
}
function dissoc($mw, $dw)
{
if (is_array($dw)) {
foreach ($dw as $k) {
unset($mw[$k]);
}
} else {
unset($mw[$dw]);
}
return $mw;
}
function sortIdx($ab)
{
$su = [];
foreach ($ab as $bx => $by) {
$su[$by] = ['_sort' => $bx + 1];
}
return $su;
}
function insertAt($ms, $sv, $l)
{
array_splice($ms, $sv, 0, [$l]);
return $ms;
}
function getArg($nq, $sw, $mx = '')
{
if (isset($nq[$sw])) {
return $nq[$sw];
} else {
return $mx;
}
}
function permu($au, $ds = ',')
{
$ai = [];
if (is_string($au)) {
$sx = str_split($au);
} else {
$sx = $au;
}
sort($sx);
$sy = count($sx) - 1;
$sz = $sy;
$ap = 1;
$dz = implode($ds, $sx);
$ai[] = $dz;
while (true) {
$tu = $sz--;
if ($sx[$sz] < $sx[$tu]) {
$tv = $sy;
while ($sx[$sz] > $sx[$tv]) {
$tv--;
}
list($sx[$sz], $sx[$tv]) = array($sx[$tv], $sx[$sz]);
for ($ew = $sy; $ew > $tu; $ew--, $tu++) {
list($sx[$ew], $sx[$tu]) = array($sx[$tu], $sx[$ew]);
}
$dz = implode($ds, $sx);
$ai[] = $dz;
$sz = $sy;
$ap++;
}
if ($sz == 0) {
break;
}
}
return $ai;
}
function combin($bc, $tw, $tx = ',')
{
$eq = array();
if ($tw == 1) {
return $bc;
}
if ($tw == count($bc)) {
$eq[] = implode($tx, $bc);
return $eq;
}
$ty = $bc[0];
unset($bc[0]);
$bc = array_values($bc);
$tz = combin($bc, $tw - 1, $tx);
foreach ($tz as $uv) {
$uv = $ty . $tx . $uv;
$eq[] = $uv;
}
unset($tz);
$uw = combin($bc, $tw, $tx);
foreach ($uw as $uv) {
$eq[] = $uv;
}
unset($uw);
return $eq;
}
function getExcelCol($df)
{
$bc = array(0 => 'Z', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z');
if ($df == 0) {
return '';
}
return getExcelCol((int) (($df - 1) / 26)) . $bc[$df % 26];
}
function getExcelPos($du, $df)
{
return getExcelCol($df) . $du;
}
function sendJSON($ab)
{
$ux = cfg::get('aca');
if (isset($ux['origin'])) {
header("Access-Control-Allow-Origin: {$ux['origin']}");
}
$uy = "Content-Type, Authorization, Accept,X-Requested-With";
if (isset($ux['headers'])) {
$uy = $ux['headers'];
}
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: {$uy}");
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
function succ($bc = array(), $uz = 'succ', $vw = 1)
{
$ab = $bc;
$vx = 0;
$vy = 1;
$ap = 0;
$v = array($uz => $vw, 'errormsg' => '', 'errorfield' => '');
if (isset($bc['data'])) {
$ab = $bc['data'];
}
$v['data'] = $ab;
if (isset($bc['total_page'])) {
$v['total_page'] = $bc['total_page'];
}
if (isset($bc['cur_page'])) {
$v['cur_page'] = $bc['cur_page'];
}
if (isset($bc['count'])) {
$v['count'] = $bc['count'];
}
if (isset($bc['res-name'])) {
$v['res-name'] = $bc['res-name'];
}
if (isset($bc['meta'])) {
$v['meta'] = $bc['meta'];
}
sendJSON($v);
}
function fail($bc = array(), $uz = 'succ', $vz = 0)
{
$k = $ps = '';
if (count($bc) > 0) {
$dw = array_keys($bc);
$k = $dw[0];
$ps = $bc[$k][0];
}
$v = array($uz => $vz, 'errormsg' => $ps, 'errorfield' => $k);
sendJSON($v);
}
function code($bc = array(), $fq = 0)
{
if (is_string($fq)) {
}
if ($fq == 0) {
succ($bc, 'code', 0);
} else {
fail($bc, 'code', $fq);
}
}
function ret($bc = array(), $fq = 0, $jm = '')
{
$rw = $bc;
$wx = $fq;
if (is_numeric($bc) || is_string($bc)) {
$wx = $bc;
$rw = array();
if (is_array($fq)) {
$rw = $fq;
} else {
$fq = $fq === 0 ? '' : $fq;
$rw = array($jm => array($fq));
}
}
code($rw, $wx);
}
function response($bc = array(), $fq = 0, $jm = '')
{
ret($bc, $fq, $jm);
}
function err($wy)
{
code($wy, 1);
}
function downloadExcel($wz, $et)
{
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition:inline;filename="' . $et . '.xls"');
header("Content-Transfer-Encoding: binary");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
$wz->save('php://output');
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
function curl($fm, $xy = 10, $xz = 30, $yz = '', $ho = 'post')
{
$abc = curl_init($fm);
curl_setopt($abc, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($abc, CURLOPT_CONNECTTIMEOUT, $xy);
curl_setopt($abc, CURLOPT_HEADER, 0);
curl_setopt($abc, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($abc, CURLOPT_TIMEOUT, $xz);
if (file_exists(cacert_file())) {
curl_setopt($abc, CURLOPT_CAINFO, cacert_file());
}
if ($yz) {
if (is_array($yz)) {
$yz = http_build_query($yz);
}
if ($ho == 'post') {
curl_setopt($abc, CURLOPT_POST, 1);
} else {
if ($ho == 'put') {
curl_setopt($abc, CURLOPT_CUSTOMREQUEST, "put");
}
}
curl_setopt($abc, CURLOPT_POSTFIELDS, $yz);
}
$eq = curl_exec($abc);
if (curl_errno($abc)) {
return '';
}
curl_close($abc);
return $eq;
}
function curl_header($fm, $xy = 10, $xz = 30)
{
$abc = curl_init($fm);
curl_setopt($abc, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($abc, CURLOPT_CONNECTTIMEOUT, $xy);
curl_setopt($abc, CURLOPT_HEADER, 1);
curl_setopt($abc, CURLOPT_NOBODY, 1);
curl_setopt($abc, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($abc, CURLOPT_TIMEOUT, $xz);
if (file_exists(cacert_file())) {
curl_setopt($abc, CURLOPT_CAINFO, cacert_file());
}
$eq = curl_exec($abc);
if (curl_errno($abc)) {
return '';
}
return $eq;
}
function http($fm, $cg = array())
{
$xy = getArg($cg, 'connecttime', 10);
$xz = getArg($cg, 'timeout', 30);
$ab = getArg($cg, 'data', '');
$ho = getArg($cg, 'method', 'get');
$uy = getArg($cg, 'headers', null);
$abc = curl_init($fm);
curl_setopt($abc, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($abc, CURLOPT_CONNECTTIMEOUT, $xy);
curl_setopt($abc, CURLOPT_HEADER, 0);
curl_setopt($abc, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($abc, CURLOPT_TIMEOUT, $xz);
if (file_exists(cacert_file())) {
curl_setopt($abc, CURLOPT_CAINFO, cacert_file());
}
if ($uy) {
curl_setopt($abc, CURLOPT_HTTPHEADER, $uy);
}
if ($ab) {
curl_setopt($abc, CURLOPT_POST, 1);
if (is_array($ab)) {
$ab = http_build_query($ab);
}
curl_setopt($abc, CURLOPT_POSTFIELDS, $ab);
}
if ($ho != 'get') {
if ($ho == 'post') {
curl_setopt($abc, CURLOPT_POST, 1);
} else {
if ($ho == 'put') {
curl_setopt($abc, CURLOPT_CUSTOMREQUEST, "put");
}
}
}
$eq = curl_exec($abc);
if (curl_errno($abc)) {
return '';
}
curl_close($abc);
return $eq;
}
function startWith($bw, $uv)
{
return strpos($bw, $uv) === 0;
}
function endWith($abd, $abe)
{
$abf = strlen($abe);
if ($abf == 0) {
return true;
}
return substr($abd, -$abf) === $abe;
}
function is_string_column($c)
{
if (startWith(strtolower($c), 'char') || startWith(strtolower($c), 'varchar') || startWith(strtolower($c), 'datetime')) {
return 2;
} else {
return 1;
}
}
function getWhereStr($k, $ab, $abg = false, $jm = '')
{
$mw = getKeyValues($ab, $k);
if (!$mw) {
return '';
}
if ($abg) {
foreach ($mw as $bx => $by) {
$mw[$bx] = "'{$by}'";
}
}
$bw = implode(',', $mw);
if ($jm) {
$k = $jm;
}
return " {$k} in ({$bw})";
}
function get_top_domain($fm)
{
$gp = "/[\\w-]+\\.(com|net|org|gov|cc|biz|info|cn)(\\.(cn|hk))*/";
preg_match($gp, $fm, $abh);
if (count($abh) > 0) {
return $abh[0];
} else {
$abi = parse_url($fm);
$abj = $abi["host"];
if (!strcmp(long2ip(sprintf("%u", ip2long($abj))), $abj)) {
return $abj;
} else {
$bc = explode(".", $abj);
$ap = count($bc);
$abk = array("com", "net", "org", "3322");
if (in_array($bc[$ap - 2], $abk)) {
$hk = $bc[$ap - 3] . "." . $bc[$ap - 2] . "." . $bc[$ap - 1];
} else {
$hk = $bc[$ap - 2] . "." . $bc[$ap - 1];
}
return $hk;
}
}
}
function genID($ow)
{
list($abl, $abm) = explode(" ", microtime());
$abn = rand(0, 100);
return $ow . $abm . substr($abl, 2, 6);
}
function cguid($abo = false)
{
mt_srand((double) microtime() * 10000);
$abp = md5(uniqid(rand(), true));
return $abo ? strtoupper($abp) : $abp;
}
function guid()
{
if (function_exists('com_create_guid')) {
return com_create_guid();
} else {
$abq = cguid();
$abr = chr(45);
$abs = chr(123) . substr($abq, 0, 8) . $abr . substr($abq, 8, 4) . $abr . substr($abq, 12, 4) . $abr . substr($abq, 16, 4) . $abr . substr($abq, 20, 12) . chr(125);
return $abs;
}
}
function randstr($ls = 6)
{
return substr(md5(rand()), 0, $ls);
}
function hashsalt($cf, $abt = '')
{
$abt = $abt ? $abt : randstr(10);
$abu = md5(md5($cf) . $abt);
return [$abu, $abt];
}
function gen_letters($ls = 26)
{
$uv = '';
for ($ew = 65; $ew < 65 + $ls; $ew++) {
$uv .= strtolower(chr($ew));
}
return $uv;
}
function gen_sign($az, $aw = null)
{
if ($aw == null) {
return false;
}
return strtoupper(md5(strtoupper(md5(assemble($az))) . $aw));
}
function assemble($az)
{
if (!is_array($az)) {
return null;
}
ksort($az, SORT_STRING);
$abv = '';
foreach ($az as $k => $u) {
$abv .= $k . (is_array($u) ? assemble($u) : $u);
}
return $abv;
}
function check_sign($az, $aw = null)
{
$abv = getArg($az, 'sign');
$abw = getArg($az, 'date');
$abx = strtotime($abw);
$aby = time();
$abz = $aby - $abx;
debug("check_sign : {$aby} - {$abx} = {$abz}");
if (!$abw || $aby - $abx > 60) {
debug("check_sign fail : {$abw} delta > 60");
return false;
}
unset($az['sign']);
$acd = gen_sign($az, $aw);
debug("{$abv} -- {$acd}");
return $abv == $acd;
}
function getIP()
{
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
$ace = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
$ace = $_SERVER["HTTP_CLIENT_IP"];
} else {
if (!empty($_SERVER["REMOTE_ADDR"])) {
$ace = $_SERVER["REMOTE_ADDR"];
} else {
if (getenv("HTTP_X_FORWARDED_FOR")) {
$ace = getenv("HTTP_X_FORWARDED_FOR");
} else {
if (getenv("HTTP_CLIENT_IP")) {
$ace = getenv("HTTP_CLIENT_IP");
} else {
if (getenv("REMOTE_ADDR")) {
$ace = getenv("REMOTE_ADDR");
} else {
$ace = "Unknown";
}
}
}
}
}
}
return $ace;
}
function getRIP()
{
$ace = $_SERVER["REMOTE_ADDR"];
return $ace;
}
function env($k = 'DEV_MODE', $mx = '')
{
$l = getenv($k);
return $l ? $l : $mx;
}
function vpath()
{
$bs = getenv("VENDER_PATH");
if ($bs) {
return $bs;
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
$acf = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
if (preg_match("/(" . implode('|', $acf) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
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
function cache($k, $de = null, $abm = 10, $acg = 0)
{
$ach = new FilesystemCache();
if ($de) {
if (is_callable($de)) {
if ($acg || !$ach->has($k)) {
$ab = $de();
debug("--------- fn: no cache for [{$k}] ----------");
$ach->set($k, $ab, $abm);
} else {
$ab = $ach->get($k);
debug("======= fn: data from cache [{$k}] ========");
}
} else {
debug("--------- set cache for [{$k}] ---------- : " . json_encode($de));
$ach->set($k, $de, $abm);
$ab = $de;
}
} else {
debug("--------- get cache for [{$k}] ---------- ");
$ab = $ach->get($k);
}
return $ab;
}
function cache_del($k)
{
$ach = new FilesystemCache();
$ach->delete($k);
debug("!!!!!!--------- delete cache for [{$k}] ----------!!!!!!");
}
function cache_clear()
{
$ach = new FilesystemCache();
$ach->clear();
debug("!!!!!!--------- clear all cache ----------!!!!!!");
}
function baseModel($aci)
{
return '<' . <<<EOF
?php
namespace Entities {
class {$aci}
{
    protected \$id;
    protected \$intm;
    protected \$st;

EOF;
}
function baseArray($aci, $ei)
{
return array("Entities\\{$aci}" => array('type' => 'entity', 'table' => $ei, 'id' => array('id' => array('type' => 'integer', 'id' => true, 'generator' => array('strategy' => 'IDENTITY'))), 'fields' => array('intm' => array('type' => 'datetime', 'column' => '_intm'), 'st' => array('type' => 'integer', 'column' => '_st', 'options' => array('default' => 1)))));
}
function genObj($aci)
{
$jk = array_merge(\db::col_array('sys_objects'), ['sys_object_item.name(itemname)', 'sys_object_item.colname', 'sys_object_item.type', 'sys_object_item.length', 'sys_object_item.default', 'sys_object_item.comment']);
$ds = ['[>]sys_object_item' => ['id' => 'oid']];
$ef = ['AND' => ['sys_objects.name' => $aci], 'ORDER' => ['sys_objects.id' => 'DESC']];
$dj = \db::all('sys_objects', $ef, $jk, $ds);
if ($dj) {
$ei = $dj[0]['table'];
$ab = baseArray($aci, $ei);
$acj = baseModel($aci);
foreach ($dj as $du) {
if (!$du['itemname']) {
continue;
}
$ack = $du['colname'] ? $du['colname'] : $du['itemname'];
$jm = ['type' => "{$du['type']}", 'column' => "{$ack}", 'options' => array('default' => "{$du['default']}", 'comment' => "{$du['comment']}")];
$ab['Entities\\' . $aci]['fields'][$du['itemname']] = $jm;
$acj .= "    protected \${$du['itemname']}; \n";
}
$acj .= '}}';
}
return [$ab, $acj];
}
function writeObjFile($aci)
{
list($ab, $acj) = genObj($aci);
$acl = \Symfony\Component\Yaml\Yaml::dump($ab);
$acm = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$acn = $acm . '/src/objs';
if (!is_dir($acn)) {
mkdir($acn);
}
file_put_contents("{$acn}/{$aci}.php", $acj);
file_put_contents("{$acn}/Entities.{$aci}.dcm.yml", $acl);
}
function sync_to_db($aco = 'run')
{
echo $aco;
$acm = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$aco = "cd {$acm} && sh ./{$aco}.sh";
exec($aco, $mw);
foreach ($mw as $dz) {
echo \SqlFormatter::format($dz);
}
}
function gen_schema($acp, $acq, $acr = false, $acs = false)
{
$act = true;
$acu = ROOT_PATH . '/tools/bin/db';
$acv = [$acu . "/yml", $acu . "/src/objs"];
$e = \Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration($acv, $act);
$acw = \Doctrine\ORM\EntityManager::create($acp, $e);
$acx = $acw->getConnection()->getDatabasePlatform();
$acx->registerDoctrineTypeMapping('enum', 'string');
$acy = [];
foreach ($acq as $acz) {
$ade = $acz['name'];
include_once "{$acu}/src/objs/{$ade}.php";
$acy[] = $acw->getClassMetadata('Entities\\' . $ade);
}
$adf = new \Doctrine\ORM\Tools\SchemaTool($acw);
$adg = $adf->getUpdateSchemaSql($acy, true);
if (!$adg) {
}
$adh = [];
$adi = [];
foreach ($adg as $dz) {
if (startWith($dz, 'DROP')) {
$adh[] = $dz;
}
$adi[] = \SqlFormatter::format($dz);
}
if ($acr && !$adh || $acs) {
$v = $adf->updateSchema($acy, true);
}
return $adi;
}
function gen_dbc_schema($acq)
{
$adj = \db::dbc();
$acp = ['driver' => 'pdo_mysql', 'host' => $adj['server'], 'user' => $adj['username'], 'password' => $adj['password'], 'dbname' => $adj['database_name']];
$acr = get('write', false);
$adk = get('force', false);
$adg = gen_schema($acp, $acq, $acr, $adk);
return ['database' => $adj['database_name'], 'sqls' => $adg];
}
function gen_corp_schema($cu, $acq)
{
\db::switch_dbc($cu);
return gen_dbc_schema($acq);
}
function buildcmd($cg = array())
{
$adl = new ptlis\ShellCommand\CommandBuilder();
$nq = ['LC_CTYPE=en_US.UTF-8'];
if (isset($cg['args'])) {
$nq = $cg['args'];
}
if (isset($cg['add_args'])) {
$nq = array_merge($nq, $cg['add_args']);
}
$adm = $adl->setCommand('/usr/bin/env')->addArguments($nq)->buildCommand();
return $adm;
}
function exec_git($cg = array())
{
$bs = '.';
if (isset($cg['path'])) {
$bs = $cg['path'];
}
$nq = ["/usr/bin/git", "--git-dir={$bs}/.git", "--work-tree={$bs}"];
$aco = 'status';
if (isset($cg['cmd'])) {
$aco = $cg['cmd'];
}
$nq[] = $aco;
$adm = buildcmd(['add_args' => $nq, $aco]);
$eq = $adm->runSynchronous();
return $eq->getStdOutLines();
}
use db\Rest as rest;
function getMetaData($aci, $adn = array())
{
ctx::pagesize(50);
$acq = db::all('sys_objects');
$ado = array_filter($acq, function ($by) use($aci) {
return $by['name'] == $aci;
});
$ado = array_shift($ado);
$adp = $ado['id'];
$adq = db::all('sys_object_item', ['oid' => $adp]);
$adr = ['Id'];
$ads = [0];
$adt = [0.1];
$dr = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true]];
foreach ($adq as $dz) {
$bu = $dz['name'];
$ack = $dz['colname'] ? $dz['colname'] : $bu;
$c = $dz['type'];
$mx = $dz['default'];
$adu = $dz['col_width'];
$adv = $dz['readonly'] ? ture : false;
$adw = $dz['is_meta'];
if ($adw) {
$adr[] = $bu;
$ads[$ack] = $bu;
$adt[] = (double) $adu;
if (in_array($ack, array_keys($adn))) {
$dr[] = $adn[$ack];
} else {
$dr[] = ['data' => $ack, 'renderer' => 'html', 'readOnly' => $adv];
}
}
}
$adr[] = "InTm";
$adr[] = "St";
$adt[] = 60;
$adt[] = 10;
$dr[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$dr[] = ['data' => "_st", 'renderer' => "html"];
$ks = ['objname' => $aci];
return [$ks, $adr, $ads, $adt, $dr];
}
function getHotData($aci, $adn = array())
{
$adr[] = "InTm";
$adr[] = "St";
$adt[] = 60;
$adt[] = 10;
$dr[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$dr[] = ['data' => "_st", 'renderer' => "html"];
$ks = ['objname' => $aci];
return [$ks, $adr, $adt, $dr];
}
function fixfn($cj)
{
foreach ($cj as $ck) {
if (!function_exists($ck)) {
eval("function {$ck}(){}");
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
function ms($bu)
{
return \ctx::container()->ms->getService($bu);
}
function rms($bu, $x = 'rest')
{
return \ctx::container()->ms->getRest($bu, $x);
}
function idxtree($adx, $ady)
{
$js = [];
$ab = \db::all($adx, ['pid' => $ady]);
$adz = getKeyValues($ab, 'id');
if ($adz) {
foreach ($adz as $ady) {
$js = array_merge($js, idxtree($adx, $ady));
}
}
return array_merge($adz, $js);
}
function treelist($adx, $ady)
{
$oq = \db::row($adx, ['id' => $ady]);
$aef = $oq['sub_ids'];
$aef = json_decode($aef, true);
$aeg = \db::all($adx, ['id' => $aef]);
$aeh = 0;
foreach ($aeg as $bx => $aei) {
if ($aei['pid'] == $ady) {
$aeg[$bx]['pid'] = 0;
$aeh++;
}
}
if ($aeh < 2) {
$aeg[] = [];
}
return $aeg;
return array_merge([$oq], $aeg);
}
function switch_domain($aw, $cu)
{
$ak = cache($aw);
$ak['userinfo']['corpid'] = $cu;
cache_user($aw, $ak);
$aej = [];
$aek = ms('master');
if ($aek) {
$cv = ms('master')->get(['path' => '/master/corp/apps', 'data' => ['corpid' => $cu]]);
$aej = $cv->json();
$aej = getArg($aej, 'data');
}
return $aej;
}
function auto_reg_user($ael = 'username', $aem = 'password', $cx = 'user', $aen = 0)
{
$ce = randstr(10);
$cf = randstr(6);
$ab = ["{$ael}" => $ce, "{$aem}" => $cf, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
if ($aen) {
list($cf, $abt) = hashsalt($cf);
$ab[$aem] = $cf;
$ab['salt'] = $abt;
} else {
$ab[$aem] = md5($cf);
}
return db::save($cx, $ab);
}
function refresh_token($cx, $be, $hk = '')
{
$aeo = cguid();
$ab = ['id' => $be, 'token' => $aeo];
$ak = db::save($cx, $ab);
if ($hk) {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/', $hk);
} else {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/');
}
return $ak;
}
function local_user($aep, $kt, $cx = 'user')
{
return \db::row($cx, [$aep => $kt]);
}
function user_login($app, $ael = 'username', $aem = 'password', $cx = 'user', $aen = 0)
{
$ab = ctx::data();
$ab = select_keys([$ael, $aem], $ab);
$ce = $ab[$ael];
$cf = $ab[$aem];
if (!$ce || !$cf) {
return NULL;
}
$ak = \db::row($cx, ["{$ael}" => $ce]);
if ($ak) {
if ($aen) {
$abt = $ak['salt'];
list($cf, $abt) = hashsalt($cf, $abt);
} else {
$cf = md5($cf);
}
if ($cf == $ak[$aem]) {
return $ak;
}
}
return NULL;
}
function uc_auto_reg_user($fj, $aeq)
{
$v = \uc::find_user(['username' => $fj]);
if ($v['code'] != 0) {
$v = uc::reg_user($fj, $aeq);
} else {
$v = ['code' => 1, 'data' => []];
}
return $v;
}
function uc_login_data($bi)
{
$ay = uc::user_info($bi);
$ay = $ay['data'];
$fx = [];
$aer = uc::user_role($bi, 1);
$mr = [];
if ($aer['code'] == 0) {
$mr = $aer['data']['roles'];
if ($mr) {
foreach ($mr as $k => $fv) {
$fx[] = $fv['name'];
}
}
}
$ay['roles'] = $fx;
$aes = uc::user_domain($bi);
$ay['corps'] = array_values($aes['data']);
return [$bi, $ay, $mr];
}
function uc_user_login($app, $ael = 'username', $aem = 'password')
{
log_time("uc_user_login start");
$wx = $app->getContainer();
$z = $wx->request;
$ab = $z->getParams();
$ab = select_keys([$ael, $aem], $ab);
$ce = $ab[$ael];
$cf = $ab[$aem];
if (!$ce || !$cf) {
return NULL;
}
uc::init();
$v = uc::pwd_login($ce, $cf);
if ($v['code'] != 0) {
ret($v['code'], $v['message']);
}
$bi = $v['data']['access_token'];
return uc_login_data($bi);
}
function cube_user_login($app, $ael = 'username', $aem = 'password')
{
$wx = $app->getContainer();
$z = $wx->request;
$ab = $z->getParams();
if (isset($ab['code']) && isset($ab['bind_type'])) {
$e = getWxConfig('ucode');
$aet = \EasyWeChat\Factory::miniProgram($e);
$aeu = $aet->auth->session($ab['code']);
$ab = cube::openid_login($aeu['openid'], $ab['bind_type']);
} else {
$aev = select_keys([$ael, $aem], $ab);
$ce = $aev[$ael];
$cf = $aev[$aem];
if (!$ce || !$cf) {
return NULL;
}
$ab = cube::login($ce, $cf);
}
$ak = cube::user();
if (!$ak) {
return NULL;
}
$ak['user']['modules'] = cube::modules()['modules'];
$ak['passport'] = cube::$passport;
$fx = cube::roles()['roles'];
$aew = indexArray($fx, 'id');
$mr = [];
if ($ak['user']['roles']) {
foreach ($ak['user']['roles'] as &$aex) {
$aex['name'] = $aew[$aex['role_id']]['name'];
$aex['title'] = $aew[$aex['role_id']]['title'];
$aex['description'] = $aew[$aex['role_id']]['description'];
$mr[] = $aex['name'];
}
}
$ak['user']['role_list'] = $ak['user']['roles'];
$ak['user']['roles'] = $mr;
return $ak;
}
function check_auth($app)
{
$z = req();
$aey = false;
$aez = cfg::get('public_paths');
$gl = $z->getUri()->getPath();
if ($gl == '/') {
$aey = true;
} else {
foreach ($aez as $bs) {
if (startWith($gl, $bs)) {
$aey = true;
}
}
}
info("check_auth: {$aey} {$gl}");
if (!$aey) {
if (is_weixin()) {
$hl = $_SERVER['REQUEST_URI'];
header('Location: /api/auth/wechat?_r=' . $hl);
}
ret(1, 'auth error');
}
}
function extractUserData($afg)
{
return ['githubLogin' => $afg['login'], 'githubName' => $afg['name'], 'githubId' => $afg['id'], 'repos_url' => $afg['repos_url'], 'avatar_url' => $afg['avatar_url'], '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
}
function output_user($ak, $afh = false)
{
unset($ak['passwd']);
unset($ak['salt']);
if (!$afh) {
unset($ak['token']);
}
unset($ak['access-token']);
ret($ak);
}
function cache_user($aw, $ak = null)
{
cache($aw, $ak, 7200, $ak);
$ay = null;
if ($ak) {
$ay = getArg($ak, 'userinfo');
}
return ['token' => $aw, 'userinfo' => $ay];
}
if (!isset($_SERVER['REQUEST_METHOD'])) {
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/pub/psysh';
}
$app = new \Slim\App();
ctx::app($app);
function tpl($bq, $afi = '.html')
{
$bq = $bq . $afi;
$afj = cfg::get('tpl_prefix');
$afk = "{$afj['pc']}/{$bq}";
$afl = "{$afj['mobile']}/{$bq}";
info("tpl: {$afk} | {$afl}");
return isMobile() ? $afl : $afk;
}
function req()
{
return ctx::req();
}
function get($bu, $mx = '')
{
$z = req();
$u = $z->getParam($bu, $mx);
if ($u == $mx) {
$afm = ctx::gets();
if (isset($afm[$bu])) {
return $afm[$bu];
}
}
return $u;
}
function post($bu, $mx = '')
{
$z = req();
return $z->getParam($bu, $mx);
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
$gl = $z->getUri()->getPath();
if (!startWith($gl, '/')) {
$gl = '/' . $gl;
}
return $gl;
}
function host_str($uv)
{
$afn = '';
if (isset($_SERVER['HTTP_HOST'])) {
$afn = $_SERVER['HTTP_HOST'];
}
return " [ {$afn} ] " . $uv;
}
function debug($uv)
{
$m = \cfg::get('app');
if (isset($m['log_level']) && $m['log_level'] == 100) {
if (ctx::logger()) {
$uv = format_log_str($uv, getCallerStr(3));
ctx::logger()->debug(host_str($uv));
}
}
}
function warn($uv)
{
if (ctx::logger()) {
$uv = format_log_str($uv, getCallerStr(3));
ctx::logger()->warn(host_str($uv));
}
}
function info($uv)
{
if (ctx::logger()) {
$uv = format_log_str($uv, getCallerStr(3));
ctx::logger()->info(host_str($uv));
}
}
function format_log_str($uv, $afo = '')
{
if (is_array($uv)) {
$uv = json_encode($uv);
}
return "{$uv} [ ::{$afo} ]";
}
function ck_owner($dz)
{
$be = ctx::uid();
$ky = $dz['uid'];
debug("ck_owner: {$be} {$ky}");
return $be == $ky;
}
function _err($bu)
{
return cfg::get($bu, 'error');
}
$__log_time__ = 0;
$__log_begin_time__ = 0;
function log_time($bw = '', $abx = 0)
{
global $__log_time__, $__log_begin_time__;
list($abl, $abm) = explode(" ", microtime());
$afp = (double) $abl + (double) $abm;
if (!$__log_time__) {
$__log_begin_time__ = $afp;
$__log_time__ = $afp;
$bs = uripath();
debug("usetime: --- {$bs} ---");
return $afp;
}
if ($abx && $abx == 'begin') {
$afq = $__log_begin_time__;
} else {
$afq = $abx ? $abx : $__log_time__;
}
$abz = $afp - $afq;
$abz *= 1000;
debug("usetime: ---  {$abz} {$bw}  ---");
$__log_time__ = $afp;
return $afp;
}
use core\Service as ms;
$p = $app->getContainer();
$p['view'] = function ($wx) {
$br = new \Slim\Views\Twig(ROOT_PATH . '/templates', ['cache' => false]);
$br->addExtension(new \Slim\Views\TwigExtension($wx['router'], $wx['request']->getUri()));
return $br;
};
$p['logger'] = function ($wx) {
if (is_docker_env()) {
$afr = '/ws/log/app.log';
} else {
$afs = cfg::get('logdir');
if ($afs) {
$afr = $afs . '/app.log';
} else {
$afr = __DIR__ . '/../app.log';
}
}
$aft = ['name' => '', 'path' => $afr];
$afu = new \Monolog\Logger($aft['name']);
$afu->pushProcessor(new \Monolog\Processor\UidProcessor());
$afv = \cfg::get('app');
$ov = isset($afv['log_level']) ? $afv['log_level'] : '';
if (!$ov) {
$ov = \Monolog\Logger::INFO;
}
$afu->pushHandler(new \Monolog\Handler\StreamHandler($aft['path'], $ov));
return $afu;
};
log_time();
unset($app->getContainer()['phpErrorHandler']);
unset($app->getContainer()['errorHandler']);
$p['notFoundHandler'] = function ($wx) {
if (!\ctx::isFoundRoute()) {
return function ($gi, $gj) use($wx) {
return $wx['response']->withStatus(404)->withHeader('Content-Type', 'text/html')->write('Page not found');
};
}
return function ($gi, $gj) use($wx) {
return $wx['response'];
};
};
$p['ms'] = function ($wx) {
ms::init();
return new ms();
};
\Valitron\Validator::addRule('mobile', function ($jm, $l, array $az) {
return preg_match("/^1[3|4|5|7|8]\\d{9}\$/", $l) ? TRUE : FALSE;
}, 'must be mobile number');
log_time("DEPS END");
log_time("ROUTES BEGIN");
use Lead\Dir\Dir as dir;
$afw = ROOT_PATH . '/routes';
if (folder_exist($afw)) {
$q = dir::scan($afw, ['type' => 'file']);
foreach ($q as $r) {
if (basename($r) != 'routes.php' && !endWith($r, '.DS_Store')) {
require_once $r;
}
}
}
$afx = cfg::get('opt_route_list');
if ($afx) {
foreach ($afx as $aj) {
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
$afy = cache('blockly_routes_key', function () {
return db::all('sys_blockly', ['AND' => ['code_type' => 'route']]);
}, 86400, 1);
foreach ($afy as $afz) {
$agh = get('nb');
if ($agh != 1) {
@eval($afz['phpcode']);
}
}
}
log_time("ROUTES ENG");
}
namespace {
use db\Rest as rest;
function def_hot_rest($app, $bu, $ek = array())
{
$app->options("/hot/{$bu}", function () {
ret([]);
});
$app->options("/hot/{$bu}/{id}", function () {
ret([]);
});
$app->get("/hot/{$bu}", function () use($ek, $bu) {
$aci = $ek['objname'];
$agi = $bu;
$dj = rest::getList($agi);
$adn = isset($ek['cols_map']) ? $ek['cols_map'] : [];
list($ks, $adr, $ads, $adt, $dr) = getMetaData($aci, $adn);
$adt[0] = 10;
$v['data'] = ['meta' => $ks, 'list' => $dj['data'], 'colHeaders' => $adr, 'colWidths' => $adt, 'cols' => $dr];
ret($v);
});
$app->get("/hot/{$bu}/param", function () use($ek, $bu) {
$aci = $ek['objname'];
$agi = $bu;
$dj = rest::getList($agi);
list($adr, $agj, $ads, $adt, $dr, $agk) = getHotColMap1($agi, ['param_pid' => $dj['data'][0]['id']]);
$ks = ['objname' => $aci];
$adt[0] = 10;
$v['data'] = ['meta' => $ks, 'list' => [], 'colHeaders' => $adr, 'colHeaderDatas' => $ads, 'colHeaderGroupDatas' => $agj, 'colWidths' => $adt, 'cols' => $dr, 'origin_data' => $agk];
ret($v);
});
$app->post("/hot/{$bu}", function () use($ek, $bu) {
$agi = $bu;
$dj = rest::postData($agi);
ret($dj);
});
$app->put("/hot/{$bu}/{id}", function ($z, $bo, $nq) use($ek, $bu) {
$agi = $bu;
$ab = ctx::data();
if (isset($ab['trans-word']) && isset($ab[$ab['trans-from']])) {
$agl = $ab['trans-from'];
$agm = $ab['trans-to'];
$u = util\Pinyin::get($ab[$agl]);
$ab[$agm] = $u;
}
ctx::data($ab);
$dj = rest::putData($agi, $nq['id']);
ret($dj);
});
}
function getHotColMap1($agi, $cg = array())
{
$agn = get('pname', '_param');
$ago = get('oname', '_opt');
$agp = get('ename', '_opt_ext');
$agq = get('lname', 'label');
$agr = getArg($cg, 'param_pid', 0);
$ags = $agi . $agn;
$agt = $agi . $ago;
$agu = $agi . $agp;
ctx::pagesize(50);
if ($agr) {
ctx::gets('pid', $agr);
}
$dj = rest::getList($ags, $cg);
$agv = getKeyValues($dj['data'], 'id');
$az = indexArray($dj['data'], 'id');
$cg = db::all($agt, ['AND' => ['pid' => $agv]]);
$cg = indexArray($cg, 'id');
$agv = array_keys($cg);
$agw = db::all($agu, ['AND' => ['pid' => $agv]]);
$agw = groupArray($agw, 'pid');
$agx = getParamOptExt($az, $cg, $agw);
$adr = [];
$ads = [];
$agj = [];
$adt = [];
$dr = [];
foreach ($az as $k => $agy) {
$adr[] = $agy[$agq];
$agj[$agy['name']] = $agy['group_name'] ? $agy['group_name'] : $agy[$agq];
$ads[$agy['name']] = $agy[$agq];
$adt[] = $agy['width'];
$dr[$agy['name']] = ['data' => $agy['name'], 'renderer' => 'html'];
}
foreach ($agw as $k => $ey) {
$agz = '';
$ady = 0;
$ahi = $cg[$k];
$ahj = $ahi['pid'];
$agy = $az[$ahj];
$ahk = $agy[$agq];
$agz = $agy['name'];
$ahl = $agy['type'];
if ($ady) {
}
if ($agz) {
$df = ['data' => $agz, 'type' => 'autocomplete', 'strict' => false, 'source' => array_values(getKeyValues($ey, 'option'))];
if ($ahl == 'select2') {
$df['editor'] = 'select2';
$ahm = [];
foreach ($ey as $ahn) {
$dz['id'] = $ahn['id'];
$dz['text'] = $ahn['option'];
$ahm[] = $dz;
}
$df['select2Options'] = ['data' => $ahm, 'dropdownAutoWidth' => true, 'width' => 'resolve'];
unset($df['type']);
}
$dr[$agz] = $df;
}
}
$dr = array_values($dr);
return [$adr, $agj, $ads, $adt, $dr, $agx];
}
function getParamOptExt($az, $cg, $agw)
{
$ek = [];
$aho = [];
foreach ($agw as $k => $ahp) {
$ahi = $cg[$k];
$ago = $ahi['name'];
$ahj = $ahi['pid'];
foreach ($ahp as $ahn) {
$ahq = $ahn['_rownum'];
$ek[$ahj][$ahq][$ago] = $ahn['option'];
$aho[$ahj][$ahq][$ago] = $ahn;
}
}
foreach ($aho as $bv => $ahn) {
$az[$bv]['opt_exts'] = array_values($ahn);
}
foreach ($ek as $bv => $dz) {
$az[$bv]['options'] = array_values($dz);
}
$ab = array_values($az);
return $ab;
}
}
namespace {
use db\Rest as rest;
use util\Pinyin;
function def_hot_opt_rest($app, $bu, $ek = array())
{
$agi = $bu;
$ahr = "{$bu}_ext";
$app->get("/hot/{$bu}", function () use($agi, $ahr) {
$ln = get('oid');
$ady = get('pid');
$cz = "select * from `{$agi}` pp join `{$ahr}` pv\n              on pp.id = pv.`pid`\n              where pp.oid={$ln} and pp.pid={$ady}";
$dj = db::query($cz);
$ab = groupArray($dj, 'name');
$adr = ['Id', 'Oid', 'RowNum'];
$adt = [5, 5, 5];
$dr = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true], ['data' => 'oid', 'renderer' => 'html', 'readOnly' => true], ['data' => '_rownum', 'renderer' => 'html', 'readOnly' => true]];
$ai = [];
foreach ($ab as $bx => $by) {
$adr[] = $by[0]['label'];
$adt[] = $by[0]['col_width'];
$dr[] = ['data' => $bx, 'renderer' => 'html'];
$ahs = [];
foreach ($by as $k => $dz) {
$ai[$dz['_rownum']][$bx] = $dz['option'];
if ($bx == 'value') {
if (!isset($ai[$dz['_rownum']]['id'])) {
$ai[$dz['_rownum']]['id'] = $dz['id'];
$ai[$dz['_rownum']]['oid'] = $ln;
$ai[$dz['_rownum']]['_rownum'] = $dz['_rownum'];
}
}
}
}
$ai = array_values($ai);
$v['data'] = ['list' => $ai, 'colHeaders' => $adr, 'colWidths' => $adt, 'cols' => $dr];
ret($v);
});
$app->get("/hot/{$bu}_addprop", function () use($agi, $ahr) {
$ln = get('oid');
$ady = get('pid');
$aht = get('propname');
if ($aht != 'value' && !checkOptPropVal($ln, $ady, 'value', $agi, $ahr)) {
addOptProp($ln, $ady, 'value', $agi, $ahr);
}
if (!checkOptPropVal($ln, $ady, $aht, $agi, $ahr)) {
addOptProp($ln, $ady, $aht, $agi, $ahr);
}
ret([11]);
});
$app->options("/hot/{$bu}", function () {
ret([]);
});
$app->options("/hot/{$bu}/{id}", function () {
ret([]);
});
$app->post("/hot/{$bu}", function () use($agi, $ahr) {
$ab = ctx::data();
$ady = $ab['pid'];
$ln = $ab['oid'];
$ahu = getArg($ab, '_rownum');
$ahv = db::row($agi, ['AND' => ['oid' => $ln, 'pid' => $ady, 'name' => 'value']]);
if (!$ahv) {
addOptProp($ln, $ady, 'value', $agi, $ahr);
}
$ahw = $ahv['id'];
$ahx = db::obj()->max($ahr, '_rownum', ['pid' => $ahw]);
$ab = ['oid' => $ln, 'pid' => $ahw, '_rownum' => $ahx + 1];
db::save($ahr, $ab);
$v = ['oid' => $ln, '_rownum' => $ahu, 'prop' => $ahv, 'maxrow' => $ahx];
ret($v);
});
$app->put("/hot/{$bu}/{id}", function ($z, $bo, $nq) use($ahr, $agi) {
$ab = ctx::data();
$ady = $ab['pid'];
$ln = $ab['oid'];
$ahu = $ab['_rownum'];
$ahu = getArg($ab, '_rownum');
$aw = $ab['token'];
$be = $ab['uid'];
$dz = dissoc($ab, ['oid', 'pid', '_rownum', '_uptm', 'uniqid', 'token', 'uid']);
debug($dz);
$k = key($dz);
$u = $dz[$k];
$ahv = db::row($agi, ['AND' => ['pid' => $ady, 'oid' => $ln, 'name' => $k]]);
info("{$ady} {$ln} {$k}");
$ahw = $ahv['id'];
$ahy = db::obj()->has($ahr, ['AND' => ['pid' => $ahw, '_rownum' => $ahu]]);
if ($ahy) {
debug("has cell ...");
$cz = "update {$ahr} set `option`='{$u}' where _rownum={$ahu} and pid={$ahw}";
debug($cz);
db::exec($cz);
} else {
debug("has no cell ...");
$ab = ['oid' => $ln, 'pid' => $ahw, '_rownum' => $ahu, 'option' => $u];
db::save($ahr, $ab);
}
$v = ['item' => $dz, 'oid' => $ln, '_rownum' => $ahu, 'key' => $k, 'val' => $u, 'prop' => $ahv, 'sql' => $cz];
ret($v);
});
}
function checkOptPropVal($ln, $ady, $bu, $agi, $ahr)
{
return db::obj()->has($agi, ['AND' => ['name' => $bu, 'oid' => $ln, 'pid' => $ady]]);
}
function addOptProp($ln, $ady, $aht, $agi, $ahr)
{
$bu = Pinyin::get($aht);
$ab = ['oid' => $ln, 'pid' => $ady, 'label' => $aht, 'name' => $bu];
$ahv = db::save($agi, $ab);
$ab = ['_rownum' => 1, 'oid' => $ln, 'pid' => $ahv['id']];
db::save($ahr, $ab);
return $ahv;
}
}
namespace {
log_time("MID BEGIN");
$app->add(new \mid\TwigMid());
$app->add(new \mid\RestMid());
$ahz = \cfg::load('mid');
if ($ahz) {
foreach ($ahz as $bx => $m) {
$aij = "\\{$bx}";
debug("load mid: {$aij}");
$app->add(new $aij());
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