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
$ay['id'] = $ay['uid'] = getArg($ay, 'user_id');
return $ay;
}
public static function getTokenUser($az, $z)
{
$bc = $z->getParam('uid');
$ak = null;
$bd = $z->getParams();
$be = self::check_appid($bd);
if ($be && check_sign($bd, $be)) {
debug("appkey: {$be}");
$ak = ['id' => $bc, 'roles' => ['admin']];
} else {
if (self::isStateless()) {
debug("isStateless");
$ak = ['id' => $bc, 'role' => 'user'];
} else {
$aw = self::$_token;
$bf = \cfg::get('use_ucenter_oauth');
if ($bf) {
return self::getUcTokenUser($aw);
}
$bg = self::getToken($z, 'access_token');
if (self::isEnableSso()) {
debug("getTokenUserBySso");
$ak = self::getTokenUserBySso($aw);
} else {
debug("get from db");
if ($aw) {
$ak = cache($aw);
$ak = $ak['user'];
} else {
if ($bg) {
$ak = self::getAccessTokenUser($az, $bg);
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
public static function check_appid($bd)
{
$bh = getArg($bd, 'appid');
if ($bh) {
$m = cfg::get('support_service_list', 'service');
if (isset($m[$bh])) {
debug("appid: {$bh} ok");
return $m[$bh];
}
}
debug("appid: {$bh} not ok");
return '';
}
public static function getTokenUserBySso($aw)
{
$ak = ms('sso')->getuserinfo(['token' => $aw])->json();
return $ak;
}
public static function getAccessTokenUser($az, $bg)
{
$bi = \db::row('oauth_access_tokens', ['access_token' => $bg]);
if ($bi) {
$bj = strtotime($bi['expires']);
if ($bj - time() > 0) {
$ak = \db::row($az, ['id' => $bi['user_id']]);
}
}
return $ak;
}
public static function user_tbl($bk = null)
{
if ($bk) {
self::$_user_tbl = $bk;
}
return self::$_user_tbl;
}
public static function render($bl, $bm, $bn, $ab)
{
$bo = new \Slim\Views\Twig($bm, ['cache' => false]);
self::$_foundRoute = true;
return $bo->render($bl, $bn, $ab);
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
$bp = str_replace(self::$_rest_prefix, '', self::uri());
$bq = explode('/', $bp);
$br = getArg($bq, 1, '');
$bs = getArg($bq, 2, '');
return [$br, $bs];
}
public static function rest_select_add($bt = '')
{
if ($bt) {
self::$_rest_select_add = $bt;
}
return self::$_rest_select_add;
}
public static function rest_join_add($bt = '')
{
if ($bt) {
self::$_rest_join_add = $bt;
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
public static function gets($bu = '', $bv = '')
{
if (!$bu) {
return self::$_gets;
}
if (!$bv) {
return self::$_gets[$bu];
}
if ($bv == '_clear') {
$bv = '';
}
self::$_gets[$bu] = $bv;
return self::$_gets;
}
}
use Medoo\Medoo;
if (!function_exists('fixfn')) {
function fixfn($bw)
{
foreach ($bw as $bx) {
if (!function_exists($bx)) {
eval("function {$bx}(){}");
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
$bw = array('debug');
fixfn($bw);
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
public static function init($m, $by = true)
{
self::init_db($m, $by);
}
public static function conns()
{
$bz['_db'] = self::queryRow('select user() as user, database() as dbname');
self::use_master_db();
$bz['_db_master'] = self::queryRow('select user() as user, database() as dbname');
self::use_default_db();
$bz['_db_default'] = self::queryRow('select user() as user, database() as dbname');
return $bz;
}
public static function new_db($m)
{
return new Medoo($m);
}
public static function get_db_cfg($m = 'use_db')
{
if (is_string($m)) {
$m = \cfg::get_db_cfg($m);
}
$m['database_name'] = env('DB_NAME', $m['database_name']);
$m['server'] = env('DB_HOST', $m['server']);
$m['username'] = env('DB_USER', $m['username']);
$m['password'] = env('DB_PASS', $m['password']);
return $m;
}
public static function init_db($m, $by = true)
{
self::$_dbc = self::get_db_cfg($m);
$cd = self::$_dbc['database_name'];
self::$_dbc_list[$cd] = self::$_dbc;
self::$_db_list[$cd] = self::new_db(self::$_dbc);
if ($by) {
self::use_db($cd);
}
}
public static function use_db($cd)
{
self::$_db = self::$_db_list[$cd];
self::$_dbc = self::$_dbc_list[$cd];
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
public static function switch_dbc($ce)
{
$cf = ms('master')->get(['path' => '/admin/corpins', 'data' => ['corpid' => $ce]]);
$cg = $cf->json();
$cg = getArg($cg, 'data', []);
self::$_dbc = $cg;
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
$ce = getArg($ak, 'corpid');
if ($ce) {
self::switch_dbc($ce);
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
public static function desc_sql($ch)
{
if (self::db_type() == 'mysql') {
return "desc {$ch}";
} else {
if (self::db_type() == 'pgsql') {
return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$ch}'";
} else {
return '';
}
}
}
public static function table_cols($br)
{
$ci = self::$tbl_desc;
if (!isset($ci[$br])) {
$cj = self::desc_sql($br);
if ($cj) {
$ci[$br] = self::query($cj);
self::$tbl_desc = $ci;
debug("---------------- cache not found : {$br}");
} else {
debug("empty desc_sql for: {$br}");
}
}
if (!isset($ci[$br])) {
return array();
} else {
return self::$tbl_desc[$br];
}
}
public static function col_array($br)
{
$ck = function ($bv) use($br) {
return $br . '.' . $bv;
};
return getKeyValues(self::table_cols($br), 'Field', $ck);
}
public static function valid_table_col($br, $cl)
{
$cm = self::table_cols($br);
foreach ($cm as $cn) {
if ($cn['Field'] == $cl) {
$c = $cn['Type'];
return is_string_column($cn['Type']);
}
}
return false;
}
public static function tbl_data($br, $ab)
{
$cm = self::table_cols($br);
$v = [];
foreach ($cm as $cn) {
$co = $cn['Field'];
if (isset($ab[$co])) {
$v[$co] = $ab[$co];
}
}
return $v;
}
public static function test()
{
$cj = "select * from tags limit 10";
$cp = self::obj()->query($cj)->fetchAll(\PDO::FETCH_ASSOC);
var_dump($cp);
}
public static function has_st($br, $cq)
{
$cr = '_st';
return isset($cq[$cr]) || isset($cq[$br . '.' . $cr]);
}
public static function getWhere($br, $cs)
{
$cr = '_st';
if (!self::valid_table_col($br, $cr)) {
return $cs;
}
$cr = $br . '._st';
if (is_array($cs)) {
$ct = array_keys($cs);
$cu = preg_grep("/^AND\\s*#?\$/i", $ct);
$cv = preg_grep("/^OR\\s*#?\$/i", $ct);
$cw = array_diff_key($cs, array_flip(explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')));
$cq = [];
if ($cw != array()) {
$cq = $cw;
if (!self::has_st($br, $cq)) {
$cs[$cr] = 1;
$cs = ['AND' => $cs];
}
}
if (!empty($cu)) {
$l = array_values($cu);
$cq = $cs[$l[0]];
if (!self::has_st($br, $cq)) {
$cs[$l[0]][$cr] = 1;
}
}
if (!empty($cv)) {
$l = array_values($cv);
$cq = $cs[$l[0]];
if (!self::has_st($br, $cq)) {
$cs[$l[0]][$cr] = 1;
}
}
if (!isset($cs['AND']) && !self::has_st($br, $cq)) {
$cs['AND'][$cr] = 1;
}
}
return $cs;
}
public static function all_sql($br, $cs = array(), $cx = '*', $cy = null)
{
$cz = [];
if ($cy) {
$cj = self::obj()->selectContext($br, $cz, $cy, $cx, $cs);
} else {
$cj = self::obj()->selectContext($br, $cz, $cx, $cs);
}
return $cj;
}
public static function all($br, $cs = array(), $cx = '*', $cy = null)
{
$cs = self::getWhere($br, $cs);
if ($cy) {
$cp = self::obj()->select($br, $cy, $cx, $cs);
} else {
$cp = self::obj()->select($br, $cx, $cs);
}
return $cp;
}
public static function count($br, $cs = array('_st' => 1))
{
$cs = self::getWhere($br, $cs);
return self::obj()->count($br, $cs);
}
public static function row_sql($br, $cs = array(), $cx = '*', $cy = '')
{
return self::row($br, $cs, $cx, $cy, true);
}
public static function row($br, $cs = array(), $cx = '*', $cy = '', $de = null)
{
$cs = self::getWhere($br, $cs);
if (!isset($cs['LIMIT'])) {
$cs['LIMIT'] = 1;
}
if ($cy) {
if ($de) {
return self::obj()->selectContext($br, $cy, $cx, $cs);
}
$cp = self::obj()->select($br, $cy, $cx, $cs);
} else {
if ($de) {
return self::obj()->selectContext($br, $cx, $cs);
}
$cp = self::obj()->select($br, $cx, $cs);
}
if ($cp) {
return $cp[0];
} else {
return null;
}
}
public static function one($br, $cs = array(), $cx = '*', $cy = '')
{
$df = self::row($br, $cs, $cx, $cy);
$dg = '';
if ($df) {
$dh = array_keys($df);
$dg = $df[$dh[0]];
}
return $dg;
}
public static function parseUk($br, $di, $ab)
{
$dj = true;
info("uk: {$di}, " . json_encode($ab));
if (is_array($di)) {
foreach ($di as $dk) {
if (!isset($ab[$dk])) {
$dj = false;
} else {
$dl[$dk] = $ab[$dk];
}
}
} else {
if (!isset($ab[$di])) {
$dj = false;
} else {
$dl = [$di => $ab[$di]];
}
}
$dm = false;
if ($dj) {
info("has uk {$dj}");
info("where: " . json_encode($dl));
if (!self::obj()->has($br, ['AND' => $dl])) {
$dm = true;
}
} else {
$dm = true;
}
return [$dl, $dm];
}
public static function save($br, $ab, $di = 'id')
{
list($dl, $dm) = self::parseUk($br, $di, $ab);
info("isInsert: {$dm}, {$br} {$di} " . json_encode($ab));
if ($dm) {
debug("insert {$br} : " . json_encode($ab));
self::obj()->insert($br, $ab);
$ab['id'] = self::obj()->id();
} else {
debug("update {$br} " . json_encode($dl));
self::obj()->update($br, $ab, ['AND' => $dl]);
}
return $ab;
}
public static function update($br, $ab, $cs)
{
self::obj()->update($br, $ab, $cs);
}
public static function exec($cj)
{
return self::obj()->query($cj);
}
public static function query($cj)
{
return self::obj()->query($cj)->fetchAll(\PDO::FETCH_ASSOC);
}
public static function queryRow($cj)
{
$cp = self::query($cj);
if ($cp) {
return $cp[0];
} else {
return null;
}
}
public static function queryOne($cj)
{
$df = self::queryRow($cj);
return self::oneVal($df);
}
public static function oneVal($df)
{
$dg = '';
if ($df) {
$dh = array_keys($df);
$dg = $df[$dh[0]];
}
return $dg;
}
public static function updateBatch($br, $ab, $di = 'id')
{
$dn = $br;
if (!is_array($ab) || empty($dn)) {
return FALSE;
}
$cj = "UPDATE `{$dn}` SET";
foreach ($ab as $bs => $df) {
foreach ($df as $k => $u) {
$do[$k][] = "WHEN {$bs} THEN {$u}";
}
}
foreach ($do as $k => $u) {
$cj .= ' `' . trim($k, '`') . '`=CASE ' . $di . ' ' . join(' ', $u) . ' END,';
}
$cj = trim($cj, ',');
$cj .= ' WHERE ' . $di . ' IN(' . join(',', array_keys($ab)) . ')';
return self::query($cj);
}
}
class fcache
{
const FILE_LIFE_KEY = 'FILE_LIFE_KEY';
const CLEAR_ALL_KEY = 'CLEAR_ALL';
static $_instance = null;
protected $_options = array('cache_dir' => './cache', 'file_locking' => true, 'file_name_prefix' => 'cache', 'cache_file_umask' => 0777, 'file_life' => 100000);
static function &getInstance($dp = array())
{
if (self::$_instance === null) {
self::$_instance = new self($dp);
}
return self::$_instance;
}
static function &setOptions($dp = array())
{
return self::getInstance($dp);
}
private function __construct($dp = array())
{
if ($this->_options['cache_dir'] !== null) {
$bm = rtrim($this->_options['cache_dir'], '/') . '/';
$this->_options['cache_dir'] = $bm;
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
$dq =& self::getInstance();
if (!is_dir($l)) {
exit('file_cache: ' . $l . ' 不是一个有效路径 ');
}
if (!is_writable($l)) {
exit('file_cache: 路径 "' . $l . '" 不可写');
}
$l = rtrim($this->_options['cache_dir'], '/') . '/';
$dq->_options['cache_dir'] = $l;
}
static function save($ab, $bs = null, $dr = null)
{
$dq =& self::getInstance();
if (!$bs) {
if ($dq->_id) {
$bs = $dq->_id;
} else {
exit('file_cache:save() id 不能为空!');
}
}
$ds = time();
if ($dr) {
$ab[self::FILE_LIFE_KEY] = $ds + $dr;
} elseif ($dr != 0) {
$ab[self::FILE_LIFE_KEY] = $ds + $dq->_options['file_life'];
}
$r = $dq->_file($bs);
$ab = "\n" . " // mktime: " . $ds . "\n" . " return " . var_export($ab, true) . "\n?>";
$cf = $dq->_filePutContents($r, $ab);
return $cf;
}
static function load($bs)
{
$dq =& self::getInstance();
$ds = time();
if (!$dq->test($bs)) {
return false;
}
$dt = $dq->_file(self::CLEAR_ALL_KEY);
$r = $dq->_file($bs);
if (is_file($dt) && filemtime($dt) > filemtime($r)) {
return false;
}
$ab = $dq->_fileGetContents($r);
if (empty($ab[self::FILE_LIFE_KEY]) || $ds < $ab[self::FILE_LIFE_KEY]) {
unset($ab[self::FILE_LIFE_KEY]);
return $ab;
}
return false;
}
protected function _filePutContents($r, $du)
{
$dq =& self::getInstance();
$dv = false;
$dw = @fopen($r, 'ab+');
if ($dw) {
if ($dq->_options['file_locking']) {
@flock($dw, LOCK_EX);
}
fseek($dw, 0);
ftruncate($dw, 0);
$dx = @fwrite($dw, $du);
if (!($dx === false)) {
$dv = true;
}
@fclose($dw);
}
@chmod($r, $dq->_options['cache_file_umask']);
return $dv;
}
protected function _file($bs)
{
$dq =& self::getInstance();
$dy = $dq->_idToFileName($bs);
return $dq->_options['cache_dir'] . $dy;
}
protected function _idToFileName($bs)
{
$dq =& self::getInstance();
$dq->_id = $bs;
$x = $dq->_options['file_name_prefix'];
$dv = $x . '---' . $bs;
return $dv;
}
static function test($bs)
{
$dq =& self::getInstance();
$r = $dq->_file($bs);
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
$dq =& self::getInstance();
$dq->save('CLEAR_ALL', self::CLEAR_ALL_KEY);
}
static function del($bs)
{
$dq =& self::getInstance();
if (!$dq->test($bs)) {
return false;
}
$r = $dq->_file($bs);
return unlink($r);
}
}
class mdb
{
private static $_client;
private static $_db;
private static $_ins;
public static function obj($cd = 'myapp_dev')
{
if (!self::$_client) {
self::$_client = new \Sokil\Mongo\Client();
}
if (!self::$_db) {
self::$_db = self::$_client->{$cd};
}
return self::$_db;
}
public static function test()
{
$bc = 1;
$dz = self::obj()->blogs;
$ef = $dz->find()->findAll();
$ab = object2array($ef);
$eg = 1;
foreach ($ab as $bu => $eh) {
unset($eh['_id']);
unset($eh['tid']);
unset($eh['tags']);
if (isset($eh['_intm'])) {
$eh['_intm'] = date('Y-m-d H:i:s', $eh['_intm']['sec']);
}
if (isset($eh['_uptm'])) {
$eh['_uptm'] = date('Y-m-d H:i:s', $eh['_uptm']['sec']);
}
$eh['uid'] = $bc;
$v = db::save('blogs', $eh);
$eg++;
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
self::$_client = $ei = new Predis\Client(cfg::get_redis_cfg());
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
public static function init($ej = array())
{
$m = cfg::get('oauth', 'oauth')['uc'];
$m['host'] = env('UC_HOST', $m['host']);
$m['clientId'] = env('UC_CLIENT_ID', $m['clientId']);
$m['clientSecret'] = env('UC_CLIENT_SECRET', $m['clientSecret']);
$m['redirectUri'] = env('UC_REDIRECT_URI', $m['redirectUri']);
$m['username'] = env('UC_USERNAME', $m['username']);
$m['passwd'] = env('UC_PASSWD', $m['passwd']);
self::$oauth_cfg = $m;
if (isset($ej['host'])) {
self::$UC_HOST = $ej['host'];
}
}
public static function makeUrl($bp, $bd = '')
{
if (!self::$oauth_cfg) {
self::init();
}
return self::$oauth_cfg['host'] . $bp . ($bd ? '?' . $bd : '');
}
public static function pwd_login($ek = null, $el = null, $em = null, $en = null)
{
$eo = $ek ? $ek : self::$oauth_cfg['username'];
$ep = $el ? $el : self::$oauth_cfg['passwd'];
$eq = $em ? $em : self::$oauth_cfg['clientId'];
$er = $en ? $en : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $eq, 'client_secret' => $er, 'grant_type' => 'password', 'username' => $eo, 'password' => $ep];
$es = self::makeUrl(self::API['accessToken']);
$et = curl($es, 10, 30, $ab);
$v = json_decode($et, true);
self::_set_pwd_user($v);
return $v;
}
public static function get_admin_token($eu = array())
{
if (isset($eu['access_token'])) {
$bg = $eu['access_token'];
} else {
$v = self::pwd_login();
$bg = $v['data']['access_token'];
}
return $bg;
}
public static function id_login($bs, $em = null, $en = null, $ev = array())
{
$eq = $em ? $em : self::$oauth_cfg['clientId'];
$er = $en ? $en : self::$oauth_cfg['clientSecret'];
$bg = self::get_admin_token($ev);
$ab = ['client_id' => $eq, 'client_secret' => $er, 'grant_type' => 'id', 'access_token' => $bg, 'id' => $bs];
$es = self::makeUrl(self::API['userAccessToken']);
$et = curl($es, 10, 30, $ab);
$v = json_decode($et, true);
self::_set_id_user($v);
return $v;
}
public static function authurl($bh, $ew, $bg)
{
$ex = self::$oauth_cfg['host'] . "/api/sso/redirect?access_token={$bg}&app_id={$bh}&domain_id={$ew}";
return $ex;
}
public static function code_login($ey, $ez = null, $em = null, $en = null)
{
$fg = $ez ? $ez : self::$oauth_cfg['redirectUri'];
$eq = $em ? $em : self::$oauth_cfg['clientId'];
$er = $en ? $en : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $eq, 'client_secret' => $er, 'grant_type' => 'authorization_code', 'redirect_uri' => $fg, 'code' => $ey];
$es = self::makeUrl(self::API['accessToken']);
$et = curl($es, 10, 30, $ab);
$v = json_decode($et, true);
self::_set_code_user($v);
return $v;
}
public static function user_info($bg)
{
$es = self::makeUrl(self::API['user'], 'access_token=' . $bg);
$et = curl($es);
$v = json_decode($et, true);
self::_set_user_info($v);
return $v;
}
public static function reg_user($eo, $el = '123456', $ev = array())
{
$bg = self::get_admin_token($ev);
$ab = ['username' => $eo, 'password' => $el, 'access_token' => $bg];
$es = self::makeUrl(self::API['user']);
$et = curl($es, 10, 30, $ab);
$fh = json_decode($et, true);
return $fh;
}
public static function register_user($eo, $el = '123456')
{
return self::reg_user($eo, $el);
}
public static function find_user($eu = array())
{
$bg = self::get_admin_token($eu);
$bd = 'access_token=' . $bg;
if (isset($eu['username'])) {
$bd .= '&username=' . $eu['username'];
}
if (isset($eu['phone'])) {
$bd .= '&phone=' . $eu['phone'];
}
$es = self::makeUrl(self::API['finduser'], $bd);
$et = curl($es, 10, 30);
$fh = json_decode($et, true);
return $fh;
}
public static function edit_user($bg, $ab = array())
{
$es = self::makeUrl(self::API['user']);
$ab['access_token'] = $bg;
$ei = new \GuzzleHttp\Client();
$cf = $ei->request('PUT', $es, ['form_params' => $ab, 'headers' => ['X-Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded']]);
$et = $cf->getBody();
return json_decode($et, true);
}
public static function set_user_role($bg, $ew, $fi, $fj = 'guest')
{
$ab = ['access_token' => $bg, 'domain_id' => $ew, 'user_id' => $fi, 'role_name' => $fj];
$es = self::makeUrl(self::API['userRole']);
$et = curl($es, 10, 30, $ab);
return json_decode($et, true);
}
public static function user_role($bg, $ew)
{
$ab = ['access_token' => $bg, 'domain_id' => $ew];
$es = self::makeUrl(self::API['userRole']);
$es = "{$es}?access_token={$bg}&domain_id={$ew}";
$et = curl($es, 10, 30);
$v = json_decode($et, true);
self::_set_user_role($v);
return $v;
}
public static function has_role($fk)
{
if (self::$user_role && isset(self::$user_role['roles'])) {
$fl = self::$user_role['roles'];
foreach ($fl as $k => $fj) {
if ($fj['name'] == $fk) {
return true;
}
}
}
return false;
}
public static function create_domain($fm, $fn, $ev = array())
{
$bg = self::get_admin_token($ev);
$ab = ['access_token' => $bg, 'domain_name' => $fm, 'description' => $fn];
$es = self::makeUrl(self::API['createDomain']);
$et = curl($es, 10, 30, $ab);
$v = json_decode($et, true);
self::_set_id_user($v);
return $v;
}
public static function user_domain($bg)
{
$ab = ['access_token' => $bg];
$es = self::makeUrl(self::API['userdomain']);
$es = "{$es}?access_token={$bg}";
$et = curl($es, 10, 30);
$v = json_decode($et, true);
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
public static function test($br, $ab)
{
}
public static function registration($ab)
{
$bv = new Valitron\Validator($ab);
$fo = ['required' => [['name'], ['gender'], ['birthdate'], ['blood'], ['nationality'], ['country'], ['mobile'], ['emergency_contact_person'], ['emergency_mobile'], ['cloth_size']], 'length' => [['name', 4]], 'mobile' => [['mobile']]];
$bv->rules($fo);
$bv->labels(['name' => '名称', 'gender' => '性别', 'birthdate' => '生日']);
if ($bv->validate()) {
return 0;
} else {
err($bv->errors());
}
}
}
}
namespace mid {
use FastRoute\Dispatcher;
use FastRoute\RouteParser\Std as StdParser;
class TwigMid
{
public function __invoke($fp, $fq, $fr)
{
log_time("Twig Begin");
$fq = $fr($fp, $fq);
$fs = uripath($fp);
debug(">>>>>> TwigMid START : {$fs}  <<<<<<");
if ($ft = $this->getRoutePath($fp)) {
$bo = \ctx::app()->getContainer()->view;
if (\ctx::isRetJson()) {
ret($bo->data);
}
$fu = rtrim($ft, '/');
if ($fu == '/' || !$fu) {
$fu = 'index';
}
$bn = $fu;
$ab = [];
if (isset($bo->data)) {
$ab = $bo->data;
if (isset($bo->data['tpl'])) {
$bn = $bo->data['tpl'];
}
}
$ab['uid'] = \ctx::uid();
$ab['isLogin'] = \ctx::user() ? true : false;
$ab['user'] = \ctx::user();
$ab['uri'] = \ctx::uri();
$ab['t'] = time();
$ab['domain'] = \cfg::get('wechat_callback_domain');
$ab['gdata'] = \ctx::global_view_data();
debug("<<<<<< TwigMid END : {$fs} >>>>>");
log_time("Twig End");
log_time("Twig Total", 'begin');
return $bo->render($fq, tpl($bn), $ab);
} else {
return $fq;
}
}
public function getRoutePath($fp)
{
$fv = \ctx::router()->dispatch($fp);
if ($fv[0] === Dispatcher::FOUND) {
$aj = \ctx::router()->lookupRoute($fv[1]);
$fw = $aj->getPattern();
$fx = new StdParser();
$fy = $fx->parse($fw);
foreach ($fy as $fz) {
foreach ($fz as $dk) {
if (is_string($dk)) {
return $dk;
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
private $isAjax = false;
public function __invoke($fp, $fq, $fr)
{
log_time("AuthMid Begin");
$fs = uripath($fp);
debug(">>>>>> AuthMid START : {$fs}  <<<<<<");
\ctx::init($fp);
$this->check_auth($fp, $fq);
debug("<<<<<< AuthMid END : {$fs} >>>>>");
log_time("AuthMid END");
$fq = $fr($fp, $fq);
return $fq;
}
public function isAjax($bp = '')
{
if ($bp) {
if (startWith($bp, '/rest')) {
$this->isAjax = true;
}
}
return $this->isAjax;
}
public function check_auth($z, $bl)
{
list($gh, $ak, $gi) = $this->auth_cfg();
$fs = uripath($z);
$this->isAjax($fs);
if ($fs == '/') {
return true;
}
$gj = $this->check_list($gh, $fs);
if ($gj) {
$this->check_admin();
}
$gk = $this->check_list($ak, $fs);
if ($gk) {
$this->check_user();
}
$gl = $this->check_list($gi, $fs);
if (!$gl) {
$this->check_user();
}
info("check_auth: {$fs} admin:[{$gj}] user:[{$gk}] pub:[{$gl}]");
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
public function auth_error($gm = 1)
{
$gn = is_weixin();
$go = isMobile();
$gp = \cfg::get('wechat_callback_domain');
info("auth_error: errorid: {$gm}, is_weixin: {$gn} , is_mobile: {$go}");
$gq = $_SERVER['REQUEST_URI'];
if ($gn) {
header("Location: {$gp}/auth/wechat?_r={$gq}");
exit;
}
if ($go) {
header("Location: {$gp}/auth/openwechat?_r={$gq}");
exit;
}
if ($this->isAjax()) {
ret($gm, 'auth error');
} else {
header('Location: /?_r=' . $gq);
exit;
}
}
public function auth_cfg()
{
$gr = \cfg::get('auth');
return [$gr['admin'], $gr['user'], $gr['public']];
}
public function check_list($ai, $fs)
{
foreach ($ai as $bp) {
if (startWith($fs, $bp)) {
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
public function __invoke($fp, $fq, $fr)
{
$this->init($fp, $fq, $fr);
log_time("{$this->classname} Begin");
$this->path_info = uripath($fp);
debug(">>>>>> {$this->name}Mid START : {$this->path_info}  <<<<<<");
$this->handelReq($fp, $fq);
debug("<<<<<< {$this->name}Mid END : {$this->path_info} >>>>>");
log_time("{$this->classname} End");
$fq = $fr($fp, $fq);
return $fq;
}
public function handelReq($z, $bl)
{
$bp = \cfg::get($this->classname, 'mid.yml');
if (is_array($bp)) {
$this->handlePathArray($bp, $z, $bl);
} else {
if (startWith($this->path_info, $bp)) {
$this->handlePath($z, $bl);
}
}
}
public function handlePathArray($gs, $z, $bl)
{
foreach ($gs as $bp => $gt) {
if (startWith($this->path_info, $bp)) {
debug("{$this->path_info} match {$bp} {$gt}");
$this->{$gt}($z, $bl);
break;
}
}
}
public function handlePath($z, $bl)
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
public function __invoke($fp, $fq, $fr)
{
log_time("RestMid Begin");
$this->path_info = uripath($fp);
$this->rest_prefix = \cfg::get_rest_prefix();
debug(">>>>>> RestMid START : {$this->path_info}  <<<<<<");
if ($this->isRest($fp)) {
info("====== RestMid Handle REST: {$this->path_info}  =======");
if ($this->isApiDoc($fp)) {
$this->apiDoc($fp);
} else {
$this->handelRest($fp);
}
} else {
debug("====== RestMid PASS REST: {$this->path_info}  =======");
}
debug("<<<<<< RestMid END : {$this->path_info}  >>>>>");
log_time("RestMid Begin");
$fq = $fr($fp, $fq);
return $fq;
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
$bp = str_replace($this->rest_prefix, '', $this->path_info);
$bq = explode('/', $bp);
$br = getArg($bq, 1, '');
$bs = getArg($bq, 2, '');
$gt = $z->getMethod();
info(" method: {$gt}, name: {$br}, id: {$bs}");
$gu = "handle{$gt}";
$this->{$gu}($z, $br, $bs);
}
public function handleGET($z, $br, $bs)
{
if ($bs) {
rest::renderItem($br, $bs);
} else {
rest::renderList($br);
}
}
public function handlePOST($z, $br, $bs)
{
self::beforeData($br, 'post');
rest::renderPostData($br);
}
public function handlePUT($z, $br, $bs)
{
self::beforeData($br, 'put');
rest::renderPutData($br, $bs);
}
public function handleDELETE($z, $br, $bs)
{
rest::delete($z, $br, $bs);
}
public function handleOPTIONS($z, $br, $bs)
{
sendJson([]);
}
public function beforeData($br, $c)
{
$gv = \cfg::get('rest_maps', 'rest.yml');
if (isset($gv[$br])) {
$m = $gv[$br][$c];
if ($m) {
$gw = $m['xmap'];
if ($gw) {
$ab = \ctx::data();
foreach ($gw as $bu => $bv) {
unset($ab[$bv]);
}
\ctx::data($ab);
}
}
}
}
public function apiDoc($z)
{
$gx = rd::genApi();
echo $gx;
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
public static function whereStr($cs, $br)
{
$v = '';
foreach ($cs as $bu => $bv) {
$fw = '/(.*)\\{(.*)\\}/i';
$bt = preg_match($fw, $bu, $gy);
$gz = '=';
if ($gy) {
$hi = $gy[1];
$gz = $gy[2];
} else {
$hi = $bu;
}
if ($hj = db::valid_table_col($br, $hi)) {
if ($hj == 2) {
if ($gz == 'in') {
$bv = implode("','", $bv);
$v .= " and t1.{$hi} {$gz} ('{$bv}')";
} else {
$v .= " and t1.{$hi}{$gz}'{$bv}'";
}
} else {
if ($gz == 'in') {
$bv = implode(',', $bv);
$v .= " and t1.{$hi} {$gz} ({$bv})";
} else {
$v .= " and t1.{$hi}{$gz}{$bv}";
}
}
} else {
}
info("[{$br}] [{$hi}] [{$hj}] {$v}");
}
return $v;
}
public static function getSqlFrom($br, $hk, $bc, $hl, $hm, $ev = array())
{
$hn = isset($_GET['tags']) ? 1 : isset($ev['tags']) ? 1 : 0;
$ho = isset($_GET['isar']) ? 1 : 0;
$hp = RestHelper::get_rest_xwh_tags_list();
if ($hp && in_array($br, $hp)) {
$hn = 0;
}
$hq = isset($ev['force_ar']) || RestHelper::isAdmin() && $ho ? "1=1" : "t1.uid={$bc}";
if ($hn) {
$hr = isset($_GET['tags']) ? get('tags') : $ev['tags'];
if ($hr && is_array($hr) && count($hr) == 1 && !$hr[0]) {
$hr = '';
}
$hs = '';
$ht = 'not in';
if ($hr) {
if (is_string($hr)) {
$hr = [$hr];
}
$hu = implode("','", $hr);
$hs = "and `name` in ('{$hu}')";
$ht = 'in';
$hv = " from {$br} t1\n                               join tag_items t on t1.id=t.`oid`\n                               {$hk}\n                               where {$hq} and t._st=1  and t.tagid {$ht}\n                               (select id from tags where type='{$br}' {$hs} )\n                               {$hm}";
} else {
$hv = " from {$br} t1\n                              {$hk}\n                              where {$hq} and t1.id not in\n                              (select oid from tag_items where type='{$br}')\n                              {$hm}";
}
} else {
$hw = $hq;
if (RestHelper::isAdmin()) {
if ($br == RestHelper::user_tbl()) {
$hw = "t1.id={$bc}";
}
}
$hv = "from {$br} t1 {$hk} where {$hw} {$hl} {$hm}";
}
return $hv;
}
public static function getSql($br, $ev = array())
{
$bc = RestHelper::uid();
$hx = RestHelper::get('sort', '_intm');
$hy = RestHelper::get('asc', -1);
if (!db::valid_table_col($br, $hx)) {
$hx = '_intm';
}
$hy = $hy > 0 ? 'asc' : 'desc';
$hm = " order by t1.{$hx} {$hy}";
$hz = RestHelper::gets();
$hz = un_select_keys(['sort', 'asc'], $hz);
$ij = RestHelper::get('_st', 1);
$cs = dissoc($hz, ['token', '_st']);
if ($ij != 'all') {
$cs['_st'] = $ij;
}
$hl = self::whereStr($cs, $br);
$ik = RestHelper::get('search', '');
$il = RestHelper::get('search-key', '');
if ($ik && $il) {
$hl .= " and {$il} like '%{$ik}%'";
}
$im = RestHelper::select_add();
$hk = RestHelper::join_add();
$hv = self::getSqlFrom($br, $hk, $bc, $hl, $hm, $ev);
$cj = "select t1.* {$im} {$hv}";
$in = "select count(*) cnt {$hv}";
$ag = RestHelper::offset();
$af = RestHelper::pagesize();
$cj .= " limit {$ag},{$af}";
return [$cj, $in];
}
public static function getResName($br, $ev)
{
$io = getArg($ev, 'res_name', '');
if ($io) {
return $io;
}
$ip = RestHelper::get('res_id_key', '');
if ($ip) {
$iq = RestHelper::get($ip);
$br .= '_' . $iq;
}
return $br;
}
public static function getList($br, $ev = array())
{
$bc = RestHelper::uid();
list($cj, $in) = self::getSql($br, $ev);
info($cj);
$cp = db::query($cj);
$ap = (int) db::queryOne($in);
$ir = RestHelper::get_rest_join_tags_list();
if ($ir && in_array($br, $ir)) {
$is = getKeyValues($cp, 'id');
$hr = RestHelper::get_tags_by_oid($bc, $is, $br);
info("get tags ok: {$bc} {$br} " . json_encode($is));
foreach ($cp as $bu => $df) {
if (isset($hr[$df['id']])) {
$it = $hr[$df['id']];
$cp[$bu]['tags'] = getKeyValues($it, 'name');
}
}
info('set tags ok');
}
if (isset($ev['join_cols'])) {
foreach ($ev['join_cols'] as $iu => $iv) {
$iw = getArg($iv, 'jtype', '1-1');
$ix = getArg($iv, 'jkeys', []);
$iy = getArg($iv, 'jwhe', []);
$iz = getArg($iv, 'ast', ['id' => 'ASC']);
if (is_string($iv['on'])) {
$jk = 'id';
$jl = $iv['on'];
} else {
if (is_array($iv['on'])) {
$jm = array_keys($iv['on']);
$jk = $jm[0];
$jl = $iv['on'][$jk];
}
}
$is = getKeyValues($cp, $jk);
$iy[$jl] = $is;
$jn = \db::all($iu, ['AND' => $iy, 'ORDER' => $iz]);
foreach ($jn as $k => $jo) {
foreach ($cp as $bu => &$df) {
if (isset($df[$jk]) && isset($jo[$jl]) && $df[$jk] == $jo[$jl]) {
if ($iw == '1-1') {
foreach ($ix as $jp => $jq) {
$df[$jq] = $jo[$jp];
}
}
$jp = isset($iv['jkey']) ? $iv['jkey'] : $iu;
if ($iw == '1-n') {
$df[$jp][] = $jo[$jp];
}
if ($iw == '1-n-o') {
$df[$jp][] = $jo;
}
if ($iw == '1-1-o') {
$df[$jp] = $jo;
}
}
}
}
}
}
$io = self::getResName($br, $ev);
\ctx::count($ap);
$jr = ['pageinfo' => \ctx::pageinfo()];
return ['data' => $cp, 'res-name' => $io, 'count' => $ap, 'meta' => $jr];
}
public static function renderList($br)
{
ret(self::getList($br));
}
public static function getItem($br, $bs)
{
$bc = RestHelper::uid();
info("---GET---: {$br}/{$bs}");
$io = "{$br}-{$bs}";
if ($br == 'colls') {
$dk = db::row($br, ["{$br}.id" => $bs], ["{$br}.id", "{$br}.title", "{$br}.from_url", "{$br}._intm", "{$br}._uptm", "posts.content"], ['[>]posts' => ['uuid' => 'uuid']]);
} else {
if ($br == 'feeds') {
$c = RestHelper::get('type');
$js = RestHelper::get('rid');
$dk = db::row($br, ['AND' => ['uid' => $bc, 'rid' => $bs, 'type' => $c]]);
if (!$dk) {
$dk = ['rid' => $bs, 'type' => $c, 'excerpt' => '', 'title' => ''];
}
$io = "{$io}-{$c}-{$bs}";
} else {
$dk = db::row($br, ['id' => $bs]);
}
}
if ($jt = RestHelper::rest_extra_data()) {
$dk = array_merge($dk, $jt);
}
return ['data' => $dk, 'res-name' => $io, 'count' => 1];
}
public static function renderItem($br, $bs)
{
ret(self::getItem($br, $bs));
}
public static function postData($br)
{
$ab = db::tbl_data($br, RestHelper::data());
$bc = RestHelper::uid();
$hr = [];
if ($br == 'tags') {
$hr = RestHelper::get_tag_by_name($bc, $ab['name'], $ab['type']);
}
if ($hr && $br == 'tags') {
$ab = $hr[0];
} else {
info("---POST---: {$br} " . json_encode($ab));
unset($ab['token']);
$ab['_intm'] = date('Y-m-d H:i:s');
if (!isset($ab['uid'])) {
$ab['uid'] = $bc;
}
$ab = db::tbl_data($br, $ab);
$ab = db::save($br, $ab);
}
return $ab;
}
public static function renderPostData($br)
{
$ab = self::postData($br);
ret($ab);
}
public static function putData($br, $bs)
{
if ($bs == 0 || $bs == '' || trim($bs) == '') {
info(" PUT ID IS EMPTY !!!");
ret();
}
$bc = RestHelper::uid();
$ab = RestHelper::data();
unset($ab['token']);
unset($ab['uniqid']);
self::checkOwner($br, $bs, $bc);
if (isset($ab['inc'])) {
$ju = $ab['inc'];
unset($ab['inc']);
db::exec("UPDATE {$br} SET {$ju} = {$ju} + 1 WHERE id={$bs}");
}
if (isset($ab['dec'])) {
$ju = $ab['dec'];
unset($ab['dec']);
db::exec("UPDATE {$br} SET {$ju} = {$ju} - 1 WHERE id={$bs}");
}
if (isset($ab['tags'])) {
RestHelper::del_tag_by_name($bc, $bs, $br);
$hr = $ab['tags'];
foreach ($hr as $jv) {
$jw = RestHelper::get_tag_by_name($bc, $jv, $br);
if ($jw) {
$jx = $jw[0]['id'];
RestHelper::save_tag_items($bc, $jx, $bs, $br);
}
}
}
info("---PUT---: {$br}/{$bs} " . json_encode($ab));
$ab = db::tbl_data($br, RestHelper::data());
$ab['id'] = $bs;
db::save($br, $ab);
return $ab;
}
public static function renderPutData($br, $bs)
{
$ab = self::putData($br, $bs);
ret($ab);
}
public static function delete($z, $br, $bs)
{
$bc = RestHelper::uid();
self::checkOwner($br, $bs, $bc);
db::save($br, ['_st' => 0, 'id' => $bs]);
ret([]);
}
public static function checkOwner($br, $bs, $bc)
{
$cs = ['AND' => ['id' => $bs], 'LIMIT' => 1];
$cp = db::obj()->select($br, '*', $cs);
if ($cp) {
$dk = $cp[0];
} else {
$dk = null;
}
if ($dk) {
if (array_key_exists('uid', $dk)) {
$jy = $dk['uid'];
if ($br == RestHelper::user_tbl()) {
$jy = $dk['id'];
}
if ($jy != $bc && (!RestHelper::isAdmin() || !RestHelper::isAdminRest())) {
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
public static function ins($jz = null)
{
if ($jz) {
self::$_ins = $jz;
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
public static function get_tags_by_oid($bc, $is, $br)
{
return self::ins()->get_tags_by_oid($bc, $is, $br);
}
public static function get_tag_by_name($bc, $br, $c)
{
return self::ins()->get_tag_by_name($bc, $br, $c);
}
public static function del_tag_by_name($bc, $bs, $br)
{
return self::ins()->del_tag_by_name($bc, $bs, $br);
}
public static function save_tag_items($bc, $jx, $bs, $br)
{
return self::ins()->save_tag_items($bc, $jx, $bs, $br);
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
public static function get($bu, $kl = '')
{
return self::ins()->get($bu, $kl);
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
public function get_tags_by_oid($bc, $is, $br);
public function get_tag_by_name($bc, $br, $c);
public function del_tag_by_name($bc, $bs, $br);
public function save_tag_items($bc, $jx, $bs, $br);
public function isAdmin();
public function isAdminRest();
public function user_tbl();
public function data();
public function uid();
public function get($bu, $kl);
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
public function get_tags_by_oid($bc, $is, $br)
{
return tag::getTagsByOids($bc, $is, $br);
}
public function get_tag_by_name($bc, $br, $c)
{
return tag::getTagByName($bc, $br, $c);
}
public function del_tag_by_name($bc, $bs, $br)
{
return tag::delTagByOid($bc, $bs, $br);
}
public function save_tag_items($bc, $jx, $bs, $br)
{
return tag::saveTagItems($bc, $jx, $bs, $br);
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
public function get($bu, $kl)
{
return get($bu, $kl);
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
public static function getTagByName($bc, $jv, $c)
{
$hr = \db::all(self::$tbl_name, ['AND' => ['uid' => $bc, 'name' => $jv, 'type' => $c, '_st' => 1]]);
return $hr;
}
public static function delTagByOid($bc, $km, $kn)
{
info("del tag: {$bc}, {$km}, {$kn}");
$v = \db::update(self::$tbl_items_name, ['_st' => 0], ['AND' => ['uid' => $bc, 'oid' => $km, 'type' => $kn]]);
info($v);
}
public static function saveTagItems($bc, $ko, $km, $kn)
{
\db::save('tag_items', ['tagid' => $ko, 'uid' => $bc, 'oid' => $km, 'type' => $kn, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')]);
}
public static function getTagsByType($bc, $c)
{
$hr = \db::all(self::$tbl_name, ['AND' => ['uid' => $bc, 'type' => $c, '_st' => 1]]);
return $hr;
}
public static function getTagsByOid($bc, $km, $c)
{
$cj = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid={$km} and t2.type='{$c}' and t2._st=1";
$cp = \db::query($cj);
return getKeyValues($cp, 'name');
}
public static function getTagsByOids($bc, $kp, $c)
{
if (is_array($kp)) {
$kp = implode(',', $kp);
}
$cj = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid in ({$kp}) and t2.type='{$c}' and t2._st=1";
$cp = \db::query($cj);
$ab = groupArray($cp, 'oid');
return $ab;
}
public static function countByTag($bc, $jv, $c)
{
$cj = "select count(*) cnt, t1.id id from tags t1 join tag_items t2 on t1.id = t2.tagid where t1.name='{$jv}' and t1.type='{$c}' and t1.uid={$bc}";
$cp = \db::query($cj);
return [$cp[0]['cnt'], $cp[0]['id']];
}
public static function saveTag($bc, $jv, $c)
{
$ab = ['uid' => $bc, 'name' => $jv, 'type' => $c, 'count' => 1, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
$ab = \db::save('tags', $ab);
return $ab;
}
public static function countTags($bc, $kq, $br)
{
foreach ($kq as $jv) {
list($kr, $bs) = self::countByTag($bc, $jv, $br);
echo "{$jv} {$kr} {$bs} <br>";
\db::update('tags', ['count' => $kr], ['id' => $bs]);
}
}
public static function saveRepoTags($bc, $ks)
{
$br = 'stars';
echo count($ks) . "<br>";
$kq = [];
foreach ($ks as $kt) {
$ku = $kt['repoId'];
$hr = isset($kt['tags']) ? $kt['tags'] : [];
if ($hr) {
foreach ($hr as $jv) {
if (!in_array($jv, $kq)) {
$kq[] = $jv;
}
$hr = self::getTagByName($bc, $jv, $br);
if (!$hr) {
$jw = self::saveTag($bc, $jv, $br);
} else {
$jw = $hr[0];
}
$ko = $jw['id'];
$kv = getStarByRepoId($bc, $ku);
if ($kv) {
$km = $kv[0]['id'];
$kw = self::getTagsByOid($bc, $km, $br);
if ($jw && !in_array($jv, $kw)) {
self::saveTagItems($bc, $ko, $km, $br);
}
} else {
echo "-------- star for {$ku} not found <br>";
}
}
} else {
}
}
self::countTags($bc, $kq, $br);
}
public static function getTagItem($kx, $bc, $ky, $di, $kz)
{
$cj = "select * from {$ky} where {$di}={$kz} and uid={$bc}";
return $kx->query($cj)->fetchAll();
}
public static function saveItemTags($kx, $bc, $br, $lm, $di = 'id')
{
echo count($lm) . "<br>";
$kq = [];
foreach ($lm as $ln) {
$kz = $ln[$di];
$hr = isset($ln['tags']) ? $ln['tags'] : [];
if ($hr) {
foreach ($hr as $jv) {
if (!in_array($jv, $kq)) {
$kq[] = $jv;
}
$hr = getTagByName($kx, $bc, $jv, $br);
if (!$hr) {
$jw = saveTag($kx, $bc, $jv, $br);
} else {
$jw = $hr[0];
}
$ko = $jw['id'];
$kv = getTagItem($kx, $bc, $br, $di, $kz);
if ($kv) {
$km = $kv[0]['id'];
$kw = getTagsByOid($kx, $bc, $km, $br);
if ($jw && !in_array($jv, $kw)) {
saveTagItems($kx, $bc, $ko, $km, $br);
}
} else {
echo "-------- star for {$kz} not found <br>";
}
}
} else {
}
}
countTags($kx, $bc, $kq, $br);
}
}
}
namespace core {
class Auth
{
public static function login($app, $lo = 'login', $ep = 'passwd')
{
$bf = \cfg::get('use_ucenter_oauth');
$aw = cguid();
$lp = null;
$ay = null;
if ($bf) {
list($bg, $ay, $lq) = uc_user_login($app, $lo, $ep);
$ak = $ay;
$lp = ['access_token' => $bg, 'userinfo' => $ay, 'role_list' => $lq];
extract(cache_user($aw, $lp));
$ay = select_keys(['username', 'phone', 'roles', 'email'], $ay);
} else {
$az = \cfg::get('user_tbl_name');
$ak = user_login($app, $lo, $ep, $az, 1);
if ($ak) {
$ak['username'] = $ak[$lo];
$lp = ['user' => $ak];
extract(cache_user($aw, $lp));
$ay = select_keys([$lo], $ak);
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
public function __construct($lr = array())
{
$this->items = $this->getArrayItems($lr);
}
public function add($dh, $l = null)
{
if (is_array($dh)) {
foreach ($dh as $k => $l) {
$this->add($k, $l);
}
} elseif (is_null($this->get($dh))) {
$this->set($dh, $l);
}
}
public function all()
{
return $this->items;
}
public function clear($dh = null)
{
if (is_null($dh)) {
$this->items = [];
return;
}
$dh = (array) $dh;
foreach ($dh as $k) {
$this->set($k, []);
}
}
public function delete($dh)
{
$dh = (array) $dh;
foreach ($dh as $k) {
if ($this->exists($this->items, $k)) {
unset($this->items[$k]);
continue;
}
$lr =& $this->items;
$ls = explode('.', $k);
$lt = array_pop($ls);
foreach ($ls as $lu) {
if (!isset($lr[$lu]) || !is_array($lr[$lu])) {
continue 2;
}
$lr =& $lr[$lu];
}
unset($lr[$lt]);
}
}
protected function exists($lv, $k)
{
return array_key_exists($k, $lv);
}
public function get($k = null, $lw = null)
{
if (is_null($k)) {
return $this->items;
}
if ($this->exists($this->items, $k)) {
return $this->items[$k];
}
if (strpos($k, '.') === false) {
return $lw;
}
$lr = $this->items;
foreach (explode('.', $k) as $lu) {
if (!is_array($lr) || !$this->exists($lr, $lu)) {
return $lw;
}
$lr =& $lr[$lu];
}
return $lr;
}
protected function getArrayItems($lr)
{
if (is_array($lr)) {
return $lr;
} elseif ($lr instanceof self) {
return $lr->all();
}
return (array) $lr;
}
public function has($dh)
{
$dh = (array) $dh;
if (!$this->items || $dh === []) {
return false;
}
foreach ($dh as $k) {
$lr = $this->items;
if ($this->exists($lr, $k)) {
continue;
}
foreach (explode('.', $k) as $lu) {
if (!is_array($lr) || !$this->exists($lr, $lu)) {
return false;
}
$lr = $lr[$lu];
}
}
return true;
}
public function isEmpty($dh = null)
{
if (is_null($dh)) {
return empty($this->items);
}
$dh = (array) $dh;
foreach ($dh as $k) {
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
$lr = (array) $this->get($k);
$l = array_merge($lr, $this->getArrayItems($l));
$this->set($k, $l);
} elseif ($k instanceof self) {
$this->items = array_merge($this->items, $k->all());
}
}
public function pull($k = null, $lw = null)
{
if (is_null($k)) {
$l = $this->all();
$this->clear();
return $l;
}
$l = $this->get($k, $lw);
$this->delete($k);
return $l;
}
public function push($k, $l = null)
{
if (is_null($l)) {
$this->items[] = $k;
return;
}
$lr = $this->get($k);
if (is_array($lr) || is_null($lr)) {
$lr[] = $l;
$this->set($k, $lr);
}
}
public function set($dh, $l = null)
{
if (is_array($dh)) {
foreach ($dh as $k => $l) {
$this->set($k, $l);
}
return;
}
$lr =& $this->items;
foreach (explode('.', $dh) as $k) {
if (!isset($lr[$k]) || !is_array($lr[$k])) {
$lr[$k] = [];
}
$lr =& $lr[$k];
}
$lr = $l;
}
public function setArray($lr)
{
$this->items = $this->getArrayItems($lr);
}
public function setReference(array &$lr)
{
$this->items =& $lr;
}
public function toJson($k = null, $dp = 0)
{
if (is_string($k)) {
return json_encode($this->get($k), $dp);
}
$dp = $k === null ? 0 : $k;
return json_encode($this->items, $dp);
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
public function __construct($lx = '')
{
if ($lx) {
$this->service = $lx;
$ev = self::$_services[$this->service];
$ly = $ev['url'];
debug("init client: {$ly}");
$this->client = new Client(['base_uri' => $ly, 'timeout' => 12.0]);
}
}
public static function add($ev = array())
{
if ($ev) {
$br = $ev['name'];
if (!isset(self::$_services[$br])) {
self::$_services[$br] = $ev;
}
}
}
public static function init()
{
$lz = \cfg::get('service_list', 'service');
if ($lz) {
foreach ($lz as $m) {
self::add($m);
}
}
}
public function getRest($lx, $x = '/rest')
{
return $this->getService($lx, $x . '/');
}
public function getService($lx, $x = '')
{
if (isset(self::$_services[$lx])) {
if (!isset(self::$_ins[$lx])) {
self::$_ins[$lx] = new Service($lx);
}
}
if (isset(self::$_ins[$lx])) {
$jz = self::$_ins[$lx];
if ($x) {
$jz->setPrefix($x);
}
return $jz;
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
$bt = $this->body();
$ab = json_decode($bt, true);
return $ab;
}
public function body()
{
if ($this->resp) {
return $this->resp->getBody();
}
}
public function __call($gt, $mn)
{
$ev = self::$_services[$this->service];
$ly = $ev['url'];
$bh = $ev['appid'];
$be = $ev['appkey'];
$mo = getArg($mn, 0, []);
$ab = getArg($mo, 'data', []);
$ab = array_merge($ab, $_GET);
unset($mp['token']);
$ab['appid'] = $bh;
$ab['date'] = date("Y-m-d H:i:s");
$ab['sign'] = gen_sign($ab, $be);
$mq = getArg($mo, 'path', '');
$mr = getArg($mo, 'suffix', '');
$mq = $this->prefix . $mq . $mr;
$gt = strtoupper($gt);
debug("api_url: {$bh} {$be} {$ly}");
debug("api_name: {$mq} [{$gt}]");
debug("data: " . json_encode($ab));
try {
if (in_array($gt, ['GET'])) {
$ms = $mt == 'GET' ? 'query' : 'form_params';
$this->resp = $this->client->request($gt, $mq, [$ms => $ab]);
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
public function __get($mu)
{
$gt = 'get' . ucfirst($mu);
if (method_exists($this, $gt)) {
$mv = new ReflectionMethod($this, $gt);
if (!$mv->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $mu)) {
return $this->{$mu};
}
}
public function __set($mu, $l)
{
$gt = 'set' . ucfirst($mu);
if (method_exists($this, $gt)) {
$mv = new ReflectionMethod($this, $gt);
if (!$mv->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $mu)) {
$this->{$mu} = $l;
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
function __construct($ab, $ev = array())
{
$this->stack = $ab;
if (isset($ev['pid_key'])) {
$this->pid_key = $ev['pid_key'];
}
if (isset($ev['name_key'])) {
$this->name_key = $ev['name_key'];
}
if (isset($ev['children_key'])) {
$this->children_key = $ev['children_key'];
}
if (isset($ev['ext_keys'])) {
$this->ext_keys = $ev['ext_keys'];
}
if (isset($ev['pnid'])) {
$this->pick_node_id = $ev['pnid'];
}
$mw = 100;
while (count($this->stack) && $mw > 0) {
$mw -= 1;
debug("count stack: " . count($this->stack));
$this->branchify(array_shift($this->stack));
}
}
protected function branchify(&$mx)
{
if ($this->pick_node_id) {
if ($mx['id'] == $this->pick_node_id) {
$this->addLeaf($this->tree, $mx);
return;
}
} else {
if (null === $mx[$this->pid_key] || 0 == $mx[$this->pid_key]) {
$this->addLeaf($this->tree, $mx);
return;
}
}
if (isset($this->leafIndex[$mx[$this->pid_key]])) {
$this->addLeaf($this->leafIndex[$mx[$this->pid_key]][$this->children_key], $mx);
} else {
debug("back to stack: " . json_encode($mx) . json_encode($this->leafIndex));
$this->stack[] = $mx;
}
}
protected function addLeaf(&$my, $mx)
{
$mz = array('id' => $mx['id'], $this->name_key => $mx['name'], 'data' => $mx, $this->children_key => array());
foreach ($this->ext_keys as $bu => $bv) {
if (isset($mx[$bu])) {
$mz[$bv] = $mx[$bu];
}
}
$my[] = $mz;
$this->leafIndex[$mx['id']] =& $my[count($my) - 1];
}
protected function addChild($my, $mx)
{
$this->leafIndex[$mx['id']] &= $my[$this->children_key][] = $mx;
}
public function getTree()
{
return $this->tree;
}
}
}
namespace {
if (getenv('WHOOPS_ENABLED') == 'yes') {
$no = new \Whoops\Run();
$no->pushHandler(new \Whoops\Handler\PrettyPageHandler());
$no->register();
}
function getCaller($np = NULL)
{
$nq = debug_backtrace();
$nr = $nq[2];
if (isset($np)) {
return $nr[$np];
} else {
return $nr;
}
}
function getCallerStr($ns = 4)
{
$nq = debug_backtrace();
$nr = $nq[2];
$nt = $nq[1];
$nu = $nr['function'];
$nv = isset($nr['class']) ? $nr['class'] : '';
$nw = $nt['file'];
$nx = $nt['line'];
if ($ns == 4) {
$bt = "{$nv} {$nu} {$nw} {$nx}";
} elseif ($ns == 3) {
$bt = "{$nv} {$nu} {$nx}";
} else {
$bt = "{$nv} {$nx}";
}
return $bt;
}
function wlog($bp, $ny, $nz)
{
if (is_dir($bp)) {
$op = date('Y-m-d', time());
$nz .= "\n";
file_put_contents($bp . "/{$ny}-{$op}.log", $nz, FILE_APPEND);
}
}
function folder_exist($oq)
{
$bp = realpath($oq);
return ($bp !== false and is_dir($bp)) ? $bp : false;
}
use Rosio\EncryptedCookie\CryptoSystem\AES_SHA;
function encrypt($ab, $or)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$os = $m['symmetric_key'];
$ot = $m['hmac_key'];
$ou = new AES_SHA($os, $ot);
return $ou->encrypt(serialize($ab), $or);
}
function decrypt($ab)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$os = $m['symmetric_key'];
$ot = $m['hmac_key'];
$ou = new AES_SHA($os, $ot);
return unserialize($ou->decrypt($ab));
}
function encrypt_cookie($ov)
{
return encrypt($ov->getData(), $ov->getExpiration());
}
define('UC_KEY', 'iHuiPaoiwoeurqoejjdfklasdjfqowiefiqwjflkjdfsdfa');
function _authcode($du, $ow = 'DECODE', $k = '', $ox = 0)
{
$oy = 4;
$k = md5($k ? $k : UC_KEY);
$oz = md5(substr($k, 0, 16));
$pq = md5(substr($k, 16, 16));
$pr = $oy ? $ow == 'DECODE' ? substr($du, 0, $oy) : substr(md5(microtime()), -$oy) : '';
$ps = $oz . md5($oz . $pr);
$pt = strlen($ps);
$du = $ow == 'DECODE' ? base64_decode(substr($du, $oy)) : sprintf('%010d', $ox ? $ox + time() : 0) . substr(md5($du . $pq), 0, 16) . $du;
$pu = strlen($du);
$dv = '';
$pv = range(0, 255);
$pw = array();
for ($eg = 0; $eg <= 255; $eg++) {
$pw[$eg] = ord($ps[$eg % $pt]);
}
for ($px = $eg = 0; $eg < 256; $eg++) {
$px = ($px + $pv[$eg] + $pw[$eg]) % 256;
$dx = $pv[$eg];
$pv[$eg] = $pv[$px];
$pv[$px] = $dx;
}
for ($py = $px = $eg = 0; $eg < $pu; $eg++) {
$py = ($py + 1) % 256;
$px = ($px + $pv[$py]) % 256;
$dx = $pv[$py];
$pv[$py] = $pv[$px];
$pv[$px] = $dx;
$dv .= chr(ord($du[$eg]) ^ $pv[($pv[$py] + $pv[$px]) % 256]);
}
if ($ow == 'DECODE') {
if ((substr($dv, 0, 10) == 0 || substr($dv, 0, 10) - time() > 0) && substr($dv, 10, 16) == substr(md5(substr($dv, 26) . $pq), 0, 16)) {
return substr($dv, 26);
} else {
return '';
}
} else {
return $pr . str_replace('=', '', base64_encode($dv));
}
}
function object2array(&$pz)
{
$pz = json_decode(json_encode($pz), true);
return $pz;
}
function getKeyValues($ab, $k, $ck = null)
{
if (!$ck) {
$ck = function ($bv) {
return $bv;
};
}
$qr = array();
if ($ab && is_array($ab)) {
foreach ($ab as $dk) {
if (isset($dk[$k]) && $dk[$k]) {
$u = $dk[$k];
if ($ck) {
$u = $ck($u);
}
$qr[] = $u;
}
}
}
return array_unique($qr);
}
if (!function_exists('indexArray')) {
function indexArray($ab, $k, $eu = null)
{
$qr = array();
if ($ab && is_array($ab)) {
foreach ($ab as $dk) {
if (!isset($dk[$k]) || !$dk[$k] || !is_scalar($dk[$k])) {
continue;
}
if (!$eu) {
$qr[$dk[$k]] = $dk;
} else {
if (is_string($eu)) {
$qr[$dk[$k]] = $dk[$eu];
} else {
if (is_array($eu)) {
$qs = [];
foreach ($eu as $bu => $bv) {
$qs[$bv] = $dk[$bv];
}
$qr[$dk[$k]] = $dk[$eu];
}
}
}
}
}
return $qr;
}
}
if (!function_exists('groupArray')) {
function groupArray($lv, $k)
{
if (!is_array($lv) || !$lv) {
return array();
}
$ab = array();
foreach ($lv as $dk) {
if (isset($dk[$k]) && $dk[$k]) {
$ab[$dk[$k]][] = $dk;
}
}
return $ab;
}
}
function select_keys($dh, $ab)
{
$v = [];
foreach ($dh as $k) {
if (isset($ab[$k])) {
$v[$k] = $ab[$k];
} else {
$v[$k] = '';
}
}
return $v;
}
function un_select_keys($dh, $ab)
{
$v = [];
foreach ($ab as $bu => $dk) {
if (!in_array($bu, $dh)) {
$v[$bu] = $dk;
}
}
return $v;
}
function copyKey($ab, $qt, $qu)
{
foreach ($ab as &$dk) {
$dk[$qu] = $dk[$qt];
}
return $ab;
}
function addKey($ab, $k, $u)
{
foreach ($ab as &$dk) {
$dk[$k] = $u;
}
return $ab;
}
function dissoc($lv, $dh)
{
if (is_array($dh)) {
foreach ($dh as $k) {
unset($lv[$k]);
}
} else {
unset($lv[$dh]);
}
return $lv;
}
function sortIdx($ab)
{
$qv = [];
foreach ($ab as $bu => $bv) {
$qv[$bv] = ['_sort' => $bu + 1];
}
return $qv;
}
function insertAt($lr, $qw, $l)
{
array_splice($lr, $qw, 0, [$l]);
return $lr;
}
function getArg($mo, $qx, $lw = '')
{
if (isset($mo[$qx])) {
return $mo[$qx];
} else {
return $lw;
}
}
function permu($au, $cy = ',')
{
$ai = [];
if (is_string($au)) {
$qy = str_split($au);
} else {
$qy = $au;
}
sort($qy);
$qz = count($qy) - 1;
$rs = $qz;
$ap = 1;
$dk = implode($cy, $qy);
$ai[] = $dk;
while (true) {
$rt = $rs--;
if ($qy[$rs] < $qy[$rt]) {
$ru = $qz;
while ($qy[$rs] > $qy[$ru]) {
$ru--;
}
list($qy[$rs], $qy[$ru]) = array($qy[$ru], $qy[$rs]);
for ($eg = $qz; $eg > $rt; $eg--, $rt++) {
list($qy[$eg], $qy[$rt]) = array($qy[$rt], $qy[$eg]);
}
$dk = implode($cy, $qy);
$ai[] = $dk;
$rs = $qz;
$ap++;
}
if ($rs == 0) {
break;
}
}
return $ai;
}
function combin($qr, $rv, $rw = ',')
{
$dv = array();
if ($rv == 1) {
return $qr;
}
if ($rv == count($qr)) {
$dv[] = implode($rw, $qr);
return $dv;
}
$rx = $qr[0];
unset($qr[0]);
$qr = array_values($qr);
$ry = combin($qr, $rv - 1, $rw);
foreach ($ry as $rz) {
$rz = $rx . $rw . $rz;
$dv[] = $rz;
}
unset($ry);
$st = combin($qr, $rv, $rw);
foreach ($st as $rz) {
$dv[] = $rz;
}
unset($st);
return $dv;
}
function getExcelCol($cl)
{
$qr = array(0 => 'Z', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z');
if ($cl == 0) {
return '';
}
return getExcelCol((int) (($cl - 1) / 26)) . $qr[$cl % 26];
}
function getExcelPos($df, $cl)
{
return getExcelCol($cl) . $df;
}
function sendJSON($ab)
{
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept,X-Requested-With");
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
function succ($qr = array(), $su = 'succ', $sv = 1)
{
$ab = $qr;
$sw = 0;
$sx = 1;
$ap = 0;
$v = array($su => $sv, 'errormsg' => '', 'errorfield' => '');
if (isset($qr['data'])) {
$ab = $qr['data'];
}
$v['data'] = $ab;
if (isset($qr['total_page'])) {
$v['total_page'] = $qr['total_page'];
}
if (isset($qr['cur_page'])) {
$v['cur_page'] = $qr['cur_page'];
}
if (isset($qr['count'])) {
$v['count'] = $qr['count'];
}
if (isset($qr['res-name'])) {
$v['res-name'] = $qr['res-name'];
}
if (isset($qr['meta'])) {
$v['meta'] = $qr['meta'];
}
sendJSON($v);
}
function fail($qr = array(), $su = 'succ', $sy = 0)
{
$k = $nz = '';
if (count($qr) > 0) {
$dh = array_keys($qr);
$k = $dh[0];
$nz = $qr[$k][0];
}
$v = array($su => $sy, 'errormsg' => $nz, 'errorfield' => $k);
sendJSON($v);
}
function code($qr = array(), $ey = 0)
{
if (is_string($ey)) {
}
if ($ey == 0) {
succ($qr, 'code', 0);
} else {
fail($qr, 'code', $ey);
}
}
function ret($qr = array(), $ey = 0, $ju = '')
{
$py = $qr;
$sz = $ey;
if (is_numeric($qr) || is_string($qr)) {
$sz = $qr;
$py = array();
if (is_array($ey)) {
$py = $ey;
} else {
$ey = $ey === 0 ? '' : $ey;
$py = array($ju => array($ey));
}
}
code($py, $sz);
}
function err($tu)
{
code($tu, 1);
}
function downloadExcel($tv, $dy)
{
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition:inline;filename="' . $dy . '.xls"');
header("Content-Transfer-Encoding: binary");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
$tv->save('php://output');
}
function dd($ab)
{
dump($ab);
exit;
}
function cacert_file()
{
return ROOT_PATH . "/fn/cacert.pem";
}
function curl($es, $tw = 10, $tx = 30, $ty = '', $gt = 'post')
{
$tz = curl_init($es);
curl_setopt($tz, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($tz, CURLOPT_CONNECTTIMEOUT, $tw);
curl_setopt($tz, CURLOPT_HEADER, 0);
curl_setopt($tz, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($tz, CURLOPT_TIMEOUT, $tx);
if (file_exists(cacert_file())) {
curl_setopt($tz, CURLOPT_CAINFO, cacert_file());
}
if ($ty) {
if (is_array($ty)) {
$ty = http_build_query($ty);
}
if ($gt == 'post') {
curl_setopt($tz, CURLOPT_POST, 1);
} else {
if ($gt == 'put') {
curl_setopt($tz, CURLOPT_CUSTOMREQUEST, "put");
}
}
curl_setopt($tz, CURLOPT_POSTFIELDS, $ty);
}
$dv = curl_exec($tz);
if (curl_errno($tz)) {
return '';
}
curl_close($tz);
return $dv;
}
function curl_header($es, $tw = 10, $tx = 30)
{
$tz = curl_init($es);
curl_setopt($tz, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($tz, CURLOPT_CONNECTTIMEOUT, $tw);
curl_setopt($tz, CURLOPT_HEADER, 1);
curl_setopt($tz, CURLOPT_NOBODY, 1);
curl_setopt($tz, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($tz, CURLOPT_TIMEOUT, $tx);
if (file_exists(cacert_file())) {
curl_setopt($tz, CURLOPT_CAINFO, cacert_file());
}
$dv = curl_exec($tz);
if (curl_errno($tz)) {
return '';
}
return $dv;
}
function startWith($bt, $rz)
{
return strpos($bt, $rz) === 0;
}
function endWith($uv, $uw)
{
$ux = strlen($uw);
if ($ux == 0) {
return true;
}
return substr($uv, -$ux) === $uw;
}
function is_string_column($c)
{
if (startWith(strtolower($c), 'char') || startWith(strtolower($c), 'varchar') || startWith(strtolower($c), 'datetime')) {
return 2;
} else {
return 1;
}
}
function getWhereStr($k, $ab, $uy = false, $ju = '')
{
$lv = getKeyValues($ab, $k);
if (!$lv) {
return '';
}
if ($uy) {
foreach ($lv as $bu => $bv) {
$lv[$bu] = "'{$bv}'";
}
}
$bt = implode(',', $lv);
if ($ju) {
$k = $ju;
}
return " {$k} in ({$bt})";
}
function get_top_domain($es)
{
$fw = "/[\\w-]+\\.(com|net|org|gov|cc|biz|info|cn)(\\.(cn|hk))*/";
preg_match($fw, $es, $uz);
if (count($uz) > 0) {
return $uz[0];
} else {
$vw = parse_url($es);
$vx = $vw["host"];
if (!strcmp(long2ip(sprintf("%u", ip2long($vx))), $vx)) {
return $vx;
} else {
$qr = explode(".", $vx);
$ap = count($qr);
$vy = array("com", "net", "org", "3322");
if (in_array($qr[$ap - 2], $vy)) {
$gp = $qr[$ap - 3] . "." . $qr[$ap - 2] . "." . $qr[$ap - 1];
} else {
$gp = $qr[$ap - 2] . "." . $qr[$ap - 1];
}
return $gp;
}
}
}
function genID($nt)
{
list($vz, $wx) = explode(" ", microtime());
$wy = rand(0, 100);
return $nt . $wx . substr($vz, 2, 6);
}
function cguid($wz = false)
{
mt_srand((double) microtime() * 10000);
$xy = md5(uniqid(rand(), true));
return $wz ? strtoupper($xy) : $xy;
}
function guid()
{
if (function_exists('com_create_guid')) {
return com_create_guid();
} else {
$xz = cguid();
$yz = chr(45);
$abc = chr(123) . substr($xz, 0, 8) . $yz . substr($xz, 8, 4) . $yz . substr($xz, 12, 4) . $yz . substr($xz, 16, 4) . $yz . substr($xz, 20, 12) . chr(125);
return $abc;
}
}
function randstr($kr = 6)
{
return substr(md5(rand()), 0, $kr);
}
function hashsalt($ep, $abd = '')
{
$abd = $abd ? $abd : randstr(10);
$abe = md5(md5($ep) . $abd);
return [$abe, $abd];
}
function gen_letters($kr = 26)
{
$rz = '';
for ($eg = 65; $eg < 65 + $kr; $eg++) {
$rz .= strtolower(chr($eg));
}
return $rz;
}
function gen_sign($bd, $aw = null)
{
if ($aw == null) {
return false;
}
return strtoupper(md5(strtoupper(md5(assemble($bd))) . $aw));
}
function assemble($bd)
{
if (!is_array($bd)) {
return null;
}
ksort($bd, SORT_STRING);
$abf = '';
foreach ($bd as $k => $u) {
$abf .= $k . (is_array($u) ? assemble($u) : $u);
}
return $abf;
}
function check_sign($bd, $aw = null)
{
$abf = getArg($bd, 'sign');
$abg = getArg($bd, 'date');
$abh = strtotime($abg);
$abi = time();
$abj = $abi - $abh;
debug("check_sign : {$abi} - {$abh} = {$abj}");
if (!$abg || $abi - $abh > 60) {
debug("check_sign fail : {$abg} delta > 60");
return false;
}
unset($bd['sign']);
$abk = gen_sign($bd, $aw);
debug("{$abf} -- {$abk}");
return $abf == $abk;
}
function getIP()
{
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
$abl = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
$abl = $_SERVER["HTTP_CLIENT_IP"];
} else {
if (!empty($_SERVER["REMOTE_ADDR"])) {
$abl = $_SERVER["REMOTE_ADDR"];
} else {
if (getenv("HTTP_X_FORWARDED_FOR")) {
$abl = getenv("HTTP_X_FORWARDED_FOR");
} else {
if (getenv("HTTP_CLIENT_IP")) {
$abl = getenv("HTTP_CLIENT_IP");
} else {
if (getenv("REMOTE_ADDR")) {
$abl = getenv("REMOTE_ADDR");
} else {
$abl = "Unknown";
}
}
}
}
}
}
return $abl;
}
function getRIP()
{
$abl = $_SERVER["REMOTE_ADDR"];
return $abl;
}
function env($k = 'DEV_MODE', $lw = '')
{
$l = getenv($k);
return $l ? $l : $lw;
}
function vpath()
{
$bp = getenv("VENDER_PATH");
if ($bp) {
return $bp;
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
$abm = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
if (preg_match("/(" . implode('|', $abm) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
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
function cache($k, $ck = null, $wx = 10, $abn = 0)
{
$abo = new FilesystemCache();
if ($ck) {
if (is_callable($ck)) {
if ($abn || !$abo->has($k)) {
$ab = $ck();
debug("--------- fn: no cache for [{$k}] ----------");
$abo->set($k, $ab, $wx);
} else {
$ab = $abo->get($k);
debug("======= fn: data from cache [{$k}] ========");
}
} else {
debug("--------- set cache for [{$k}] ---------- : " . json_encode($ck));
$abo->set($k, $ck, $wx);
$ab = $ck;
}
} else {
debug("--------- get cache for [{$k}] ---------- ");
$ab = $abo->get($k);
}
return $ab;
}
function cache_del($k)
{
$abo = new FilesystemCache();
$abo->delete($k);
debug("!!!!!!--------- delete cache for [{$k}] ----------!!!!!!");
}
function cache_clear()
{
$abo = new FilesystemCache();
$abo->clear();
debug("!!!!!!--------- clear all cache ----------!!!!!!");
}
function baseModel($abp)
{
return '<' . <<<EOF
?php
namespace Entities {
class {$abp}
{
    protected \$id;
    protected \$intm;
    protected \$st;

EOF;
}
function baseArray($abp, $dn)
{
return array("Entities\\{$abp}" => array('type' => 'entity', 'table' => $dn, 'id' => array('id' => array('type' => 'integer', 'id' => true, 'generator' => array('strategy' => 'IDENTITY'))), 'fields' => array('intm' => array('type' => 'datetime', 'column' => '_intm'), 'st' => array('type' => 'integer', 'column' => '_st', 'options' => array('default' => 1)))));
}
function genObj($abp)
{
$abq = array_merge(\db::col_array('sys_objects'), ['sys_object_item.name(itemname)', 'sys_object_item.colname', 'sys_object_item.type', 'sys_object_item.length', 'sys_object_item.default', 'sys_object_item.comment']);
$cy = ['[>]sys_object_item' => ['id' => 'oid']];
$dl = ['AND' => ['sys_objects.name' => $abp], 'ORDER' => ['sys_objects.id' => 'DESC']];
$cp = \db::all('sys_objects', $dl, $abq, $cy);
if ($cp) {
$dn = $cp[0]['table'];
$ab = baseArray($abp, $dn);
$abr = baseModel($abp);
foreach ($cp as $df) {
if (!$df['itemname']) {
continue;
}
$abs = $df['colname'] ? $df['colname'] : $df['itemname'];
$ju = ['type' => "{$df['type']}", 'column' => "{$abs}", 'options' => array('default' => "{$df['default']}", 'comment' => "{$df['comment']}")];
$ab['Entities\\' . $abp]['fields'][$df['itemname']] = $ju;
$abr .= "    protected \${$df['itemname']}; \n";
}
$abr .= '}}';
}
return [$ab, $abr];
}
function writeObjFile($abp)
{
list($ab, $abr) = genObj($abp);
$abt = \Symfony\Component\Yaml\Yaml::dump($ab);
$abu = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$abv = $abu . '/src/objs';
if (!is_dir($abv)) {
mkdir($abv);
}
file_put_contents("{$abv}/{$abp}.php", $abr);
file_put_contents("{$abv}/Entities.{$abp}.dcm.yml", $abt);
}
function sync_to_db($abw = 'run')
{
echo $abw;
$abu = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$abw = "cd {$abu} && sh ./{$abw}.sh";
exec($abw, $lv);
foreach ($lv as $dk) {
echo \SqlFormatter::format($dk);
}
}
function gen_schema($abx, $aby, $abz = false, $acd = false)
{
$ace = true;
$acf = ROOT_PATH . '/tools/bin/db';
$acg = [$acf . "/yml", $acf . "/src/objs"];
$e = \Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration($acg, $ace);
$ach = \Doctrine\ORM\EntityManager::create($abx, $e);
$aci = $ach->getConnection()->getDatabasePlatform();
$aci->registerDoctrineTypeMapping('enum', 'string');
$acj = [];
foreach ($aby as $ack) {
$acl = $ack['name'];
include_once "{$acf}/src/objs/{$acl}.php";
$acj[] = $ach->getClassMetadata('Entities\\' . $acl);
}
$acm = new \Doctrine\ORM\Tools\SchemaTool($ach);
$acn = $acm->getUpdateSchemaSql($acj, true);
if (!$acn) {
echo "Nothing to do.";
}
$aco = [];
foreach ($acn as $dk) {
if (startWith($dk, 'DROP')) {
$aco[] = $dk;
}
echo \SqlFormatter::format($dk);
}
if ($abz && !$aco || $acd) {
$v = $acm->updateSchema($acj, true);
}
}
function gen_dbc_schema($aby)
{
$acp = \db::dbc();
$abx = ['driver' => 'pdo_mysql', 'host' => $acp['server'], 'user' => $acp['username'], 'password' => $acp['password'], 'dbname' => $acp['database_name']];
echo "Gen Schema for : {$acp['database_name']} <br>";
$abz = get('write', false);
$acq = get('force', false);
gen_schema($abx, $aby, $abz, $acq);
}
function gen_corp_schema($ce, $aby)
{
\db::switch_dbc($ce);
gen_dbc_scheam($aby);
}
function buildcmd($ev = array())
{
$acr = new ptlis\ShellCommand\CommandBuilder();
$mo = ['LC_CTYPE=en_US.UTF-8'];
if (isset($ev['args'])) {
$mo = $ev['args'];
}
if (isset($ev['add_args'])) {
$mo = array_merge($mo, $ev['add_args']);
}
$acs = $acr->setCommand('/usr/bin/env')->addArguments($mo)->buildCommand();
return $acs;
}
function exec_git($ev = array())
{
$bp = '.';
if (isset($ev['path'])) {
$bp = $ev['path'];
}
$mo = ["/usr/bin/git", "--git-dir={$bp}/.git", "--work-tree={$bp}"];
$abw = 'status';
if (isset($ev['cmd'])) {
$abw = $ev['cmd'];
}
$mo[] = $abw;
$acs = buildcmd(['add_args' => $mo, $abw]);
$dv = $acs->runSynchronous();
return $dv->getStdOutLines();
}
use db\Rest as rest;
function getMetaData($abp, $act = array())
{
ctx::pagesize(50);
$aby = db::all('sys_objects');
$acu = array_filter($aby, function ($bv) use($abp) {
return $bv['name'] == $abp;
});
$acu = array_shift($acu);
$acv = $acu['id'];
$acw = db::all('sys_object_item', ['oid' => $acv]);
$acx = ['Id'];
$acy = [0.1];
$cx = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true]];
foreach ($acw as $dk) {
$br = $dk['name'];
$abs = $dk['colname'] ? $dk['colname'] : $br;
$c = $dk['type'];
$lw = $dk['default'];
$acz = $dk['col_width'];
$ade = $dk['readonly'] ? ture : false;
$adf = $dk['is_meta'];
if ($adf) {
$acx[] = $br;
$acy[] = (double) $acz;
if (in_array($abs, array_keys($act))) {
$cx[] = $act[$abs];
} else {
$cx[] = ['data' => $abs, 'renderer' => 'html', 'readOnly' => $ade];
}
}
}
$acx[] = "InTm";
$acx[] = "St";
$acy[] = 60;
$acy[] = 10;
$cx[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$cx[] = ['data' => "_st", 'renderer' => "html"];
$jr = ['objname' => $abp];
return [$jr, $acx, $acy, $cx];
}
function getHotData($abp, $act = array())
{
$acx[] = "InTm";
$acx[] = "St";
$acy[] = 60;
$acy[] = 10;
$cx[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$cx[] = ['data' => "_st", 'renderer' => "html"];
$jr = ['objname' => $abp];
return [$jr, $acx, $acy, $cx];
}
function fixfn($bw)
{
foreach ($bw as $bx) {
if (!function_exists($bx)) {
eval("function {$bx}(){}");
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
function ms($br)
{
return \ctx::container()->ms->getService($br);
}
function rms($br, $x = 'rest')
{
return \ctx::container()->ms->getRest($br, $x);
}
function idxtree($adg, $adh)
{
$is = [];
$ab = \db::all($adg, ['pid' => $adh]);
$adi = getKeyValues($ab, 'id');
if ($adi) {
foreach ($adi as $adh) {
$is = array_merge($is, idxtree($adg, $adh));
}
}
return array_merge($adi, $is);
}
function treelist($adg, $adh)
{
$mz = \db::row($adg, ['id' => $adh]);
$adj = $mz['sub_ids'];
$adj = json_decode($adj, true);
$adk = \db::all($adg, ['id' => $adj]);
$adl = 0;
foreach ($adk as $bu => $adm) {
if ($adm['pid'] == $adh) {
$adk[$bu]['pid'] = 0;
$adl++;
}
}
if ($adl < 2) {
$adk[] = [];
}
return $adk;
return array_merge([$mz], $adk);
}
function switch_domain($aw, $ce)
{
$ak = cache($aw);
$ak['userinfo']['corpid'] = $ce;
cache_user($aw, $ak);
$cf = ms('master')->get(['path' => '/master/corp/apps', 'data' => ['corpid' => $ce]]);
$adn = $cf->json();
$adn = getArg($adn, 'data');
return $adn;
}
function auto_reg_user($ado = 'username', $adp = 'password', $ch = 'user', $adq = 0)
{
$lo = randstr(10);
$ep = randstr(6);
$ab = ["{$ado}" => $lo, "{$adp}" => $ep, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
if ($adq) {
list($ep, $abd) = hashsalt($ep);
$ab[$adp] = $ep;
$ab['salt'] = $abd;
} else {
$ab[$adp] = md5($ep);
}
return db::save($ch, $ab);
}
function refresh_token($ch, $bc, $gp = '')
{
$adr = cguid();
$ab = ['id' => $bc, 'token' => $adr];
$ak = db::save($ch, $ab);
if ($gp) {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/', $gp);
} else {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/');
}
return $ak;
}
function user_login($app, $ado = 'username', $adp = 'password', $ch = 'user', $adq = 0)
{
$ab = ctx::data();
$ab = select_keys([$ado, $adp], $ab);
$lo = $ab[$ado];
$ep = $ab[$adp];
if (!$lo || !$ep) {
return NULL;
}
$ak = \db::row($ch, ["{$ado}" => $lo]);
if ($ak) {
if ($adq) {
$abd = $ak['salt'];
list($ep, $abd) = hashsalt($ep, $abd);
} else {
$ep = md5($ep);
}
if ($ep == $ak[$adp]) {
refresh_token($ch, $ak['id']);
return $ak;
}
}
return NULL;
}
function uc_auto_reg_user($eo, $ads)
{
$v = \uc::find_user(['username' => $eo]);
if ($v['code'] != 0) {
$v = uc::reg_user($eo, $ads);
} else {
$v = ['code' => 1, 'data' => []];
}
return $v;
}
function uc_login_data($bg)
{
$ay = uc::user_info($bg);
$ay = $ay['data'];
$fl = [];
$adt = uc::user_role($bg, 1);
$lq = [];
if ($adt['code'] == 0) {
$lq = $adt['data']['roles'];
if ($lq) {
foreach ($lq as $k => $fj) {
$fl[] = $fj['name'];
}
}
}
$ay['roles'] = $fl;
$adu = uc::user_domain($bg);
$ay['corps'] = array_values($adu['data']);
return [$bg, $ay, $lq];
}
function uc_user_login($app, $ado = 'username', $adp = 'password')
{
log_time("uc_user_login start");
$sz = $app->getContainer();
$z = $sz->request;
$ab = $z->getParams();
$ab = select_keys([$ado, $adp], $ab);
$lo = $ab[$ado];
$ep = $ab[$adp];
if (!$lo || !$ep) {
return NULL;
}
uc::init();
$v = uc::pwd_login($lo, $ep);
if ($v['code'] != 0) {
ret($v['code'], $v['message']);
}
$bg = $v['data']['access_token'];
return uc_login_data($bg);
}
function check_auth($app)
{
$z = req();
$adv = false;
$adw = cfg::get('public_paths');
$fs = $z->getUri()->getPath();
if ($fs == '/') {
$adv = true;
} else {
foreach ($adw as $bp) {
if (startWith($fs, $bp)) {
$adv = true;
}
}
}
info("check_auth: {$adv} {$fs}");
if (!$adv) {
if (is_weixin()) {
$gq = $_SERVER['REQUEST_URI'];
header('Location: /api/auth/wechat?_r=' . $gq);
}
ret(1, 'auth error');
}
}
function extractUserData($adx)
{
return ['githubLogin' => $adx['login'], 'githubName' => $adx['name'], 'githubId' => $adx['id'], 'repos_url' => $adx['repos_url'], 'avatar_url' => $adx['avatar_url'], '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
}
function output_user($ak, $ady = false)
{
unset($ak['passwd']);
unset($ak['salt']);
if (!$ady) {
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
function tpl($bn, $adz = '.html')
{
$bn = $bn . $adz;
$aef = cfg::get('tpl_prefix');
$aeg = "{$aef['pc']}/{$bn}";
$aeh = "{$aef['mobile']}/{$bn}";
info("tpl: {$aeg} | {$aeh}");
return isMobile() ? $aeh : $aeg;
}
function req()
{
return ctx::req();
}
function get($br, $lw = '')
{
$z = req();
$u = $z->getParam($br, $lw);
if ($u == $lw) {
$aei = ctx::gets();
if (isset($aei[$br])) {
return $aei[$br];
}
}
return $u;
}
function post($br, $lw = '')
{
$z = req();
return $z->getParam($br, $lw);
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
$fs = $z->getUri()->getPath();
if (!startWith($fs, '/')) {
$fs = '/' . $fs;
}
return $fs;
}
function host_str($rz)
{
$aej = '';
if (isset($_SERVER['HTTP_HOST'])) {
$aej = $_SERVER['HTTP_HOST'];
}
return " [ {$aej} ] " . $rz;
}
function debug($rz)
{
$m = \cfg::get('app');
if (isset($m['log_level']) && $m['log_level'] == 100) {
if (ctx::logger()) {
$rz = format_log_str($rz, getCallerStr(3));
ctx::logger()->debug(host_str($rz));
}
}
}
function warn($rz)
{
if (ctx::logger()) {
$rz = format_log_str($rz, getCallerStr(3));
ctx::logger()->warn(host_str($rz));
}
}
function info($rz)
{
if (ctx::logger()) {
$rz = format_log_str($rz, getCallerStr(3));
ctx::logger()->info(host_str($rz));
}
}
function format_log_str($rz, $aek = '')
{
if (is_array($rz)) {
$rz = json_encode($rz);
}
return "{$rz} [ ::{$aek} ]";
}
function ck_owner($dk)
{
$bc = ctx::uid();
$jy = $dk['uid'];
debug("ck_owner: {$bc} {$jy}");
return $bc == $jy;
}
function _err($br)
{
return cfg::get($br, 'error');
}
$__log_time__ = 0;
$__log_begin_time__ = 0;
function log_time($bt = '', $abh = 0)
{
global $__log_time__, $__log_begin_time__;
list($vz, $wx) = explode(" ", microtime());
$ael = (double) $vz + (double) $wx;
if (!$__log_time__) {
$__log_begin_time__ = $ael;
$__log_time__ = $ael;
$bp = uripath();
debug("usetime: --- {$bp} ---");
return $ael;
}
if ($abh && $abh == 'begin') {
$aem = $__log_begin_time__;
} else {
$aem = $abh ? $abh : $__log_time__;
}
$abj = $ael - $aem;
$abj *= 1000;
debug("usetime: ---  {$abj} {$bt}  ---");
$__log_time__ = $ael;
return $ael;
}
use core\Service as ms;
$p = $app->getContainer();
$p['view'] = function ($sz) {
$bo = new \Slim\Views\Twig(ROOT_PATH . '/templates', ['cache' => false]);
$bo->addExtension(new \Slim\Views\TwigExtension($sz['router'], $sz['request']->getUri()));
return $bo;
};
$p['logger'] = function ($sz) {
if (is_docker_env()) {
$aen = '/ws/log/app.log';
} else {
$aeo = cfg::get('logdir');
if ($aeo) {
$aen = $aeo . '/app.log';
} else {
$aen = __DIR__ . '/../app.log';
}
}
$aep = ['name' => '', 'path' => $aen];
$aeq = new \Monolog\Logger($aep['name']);
$aeq->pushProcessor(new \Monolog\Processor\UidProcessor());
$aer = \cfg::get('app');
$ns = isset($aer['log_level']) ? $aer['log_level'] : '';
if (!$ns) {
$ns = \Monolog\Logger::INFO;
}
$aeq->pushHandler(new \Monolog\Handler\StreamHandler($aep['path'], $ns));
return $aeq;
};
log_time();
unset($app->getContainer()['phpErrorHandler']);
unset($app->getContainer()['errorHandler']);
$p['notFoundHandler'] = function ($sz) {
if (!\ctx::isFoundRoute()) {
return function ($fp, $fq) use($sz) {
return $sz['response']->withStatus(404)->withHeader('Content-Type', 'text/html')->write('Page not found');
};
}
return function ($fp, $fq) use($sz) {
return $sz['response'];
};
};
$p['ms'] = function ($sz) {
ms::init();
return new ms();
};
\Valitron\Validator::addRule('mobile', function ($ju, $l, array $bd) {
return preg_match("/^1[3|4|5|7|8]\\d{9}\$/", $l) ? TRUE : FALSE;
}, 'must be mobile number');
log_time("DEPS END");
log_time("ROUTES BEGIN");
use Lead\Dir\Dir as dir;
$aes = ROOT_PATH . '/routes';
if (folder_exist($aes)) {
$q = dir::scan($aes, ['type' => 'file']);
foreach ($q as $r) {
if (basename($r) != 'routes.php' && !endWith($r, '.DS_Store')) {
require_once $r;
}
}
}
$aet = cfg::get('opt_route_list');
if ($aet) {
foreach ($aet as $aj) {
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
$aeu = cache('blockly_routes_key', function () {
return db::all('sys_blockly', ['AND' => ['code_type' => 'route']]);
}, 86400, 1);
foreach ($aeu as $aev) {
$aew = get('nb');
if ($aew != 1) {
@eval($aev['phpcode']);
}
}
}
log_time("ROUTES ENG");
}
namespace {
use db\Rest as rest;
function def_hot_rest($app, $br, $dp = array())
{
$app->options("/hot/{$br}", function () {
ret([]);
});
$app->options("/hot/{$br}/{id}", function () {
ret([]);
});
$app->get("/hot/{$br}", function () use($dp, $br) {
$abp = $dp['objname'];
$aex = $br;
$cp = rest::getList($aex);
$act = isset($dp['cols_map']) ? $dp['cols_map'] : [];
list($jr, $acx, $acy, $cx) = getMetaData($abp, $act);
$acy[0] = 10;
$v['data'] = ['meta' => $jr, 'list' => $cp['data'], 'colHeaders' => $acx, 'colWidths' => $acy, 'cols' => $cx];
ret($v);
});
$app->get("/hot/{$br}/param", function () use($dp, $br) {
$abp = $dp['objname'];
$aex = $br;
$cp = rest::getList($aex);
list($acx, $acy, $cx) = getHotColMap1($aex);
$jr = ['objname' => $abp];
$acy[0] = 10;
$v['data'] = ['meta' => $jr, 'list' => [], 'colHeaders' => $acx, 'colWidths' => $acy, 'cols' => $cx];
ret($v);
});
$app->post("/hot/{$br}", function () use($dp, $br) {
$aex = $br;
$cp = rest::postData($aex);
ret($cp);
});
$app->put("/hot/{$br}/{id}", function ($z, $bl, $mo) use($dp, $br) {
$aex = $br;
$ab = ctx::data();
if (isset($ab['trans-word']) && isset($ab[$ab['trans-from']])) {
$aey = $ab['trans-from'];
$aez = $ab['trans-to'];
$u = util\Pinyin::get($ab[$aey]);
$ab[$aez] = $u;
}
ctx::data($ab);
$cp = rest::putData($aex, $mo['id']);
ret($cp);
});
}
function getHotColMap1($aex)
{
$afg = $aex . '_param';
$afh = $aex . '_opt';
$afi = $aex . '_opt_ext';
ctx::pagesize(50);
ctx::gets('pid', 6);
$cp = rest::getList($afg);
$afj = getKeyValues($cp['data'], 'id');
$bd = indexArray($cp['data'], 'id');
$ev = db::all($afh, ['AND' => ['pid' => $afj]]);
$ev = indexArray($ev, 'id');
$afj = array_keys($ev);
$afk = db::all($afi, ['AND' => ['pid' => $afj]]);
$afk = groupArray($afk, 'pid');
$acx = [];
$acy = [];
$cx = [];
foreach ($bd as $k => $afl) {
$acx[] = $afl['label'];
$acy[] = $afl['width'];
$cx[$afl['name']] = ['data' => $afl['name'], 'renderer' => 'html'];
}
foreach ($afk as $k => $ej) {
$afm = '';
$adh = 0;
$afn = $ev[$k];
$afo = $afn['pid'];
$afl = $bd[$afo];
$afp = $afl['label'];
$afm = $afl['name'];
if ($adh) {
}
if ($afm) {
$cx[$afm] = ['data' => $afm, 'type' => 'autocomplete', 'strict' => false, 'source' => getKeyValues($ej, 'option')];
}
}
$cx = array_values($cx);
return [$acx, $acy, $cx];
$ab = ['rows' => $cp, 'pids' => $afj, 'props' => $afq, 'opts' => $ev, 'cols_map' => $act];
$act = [];
return $act;
}
}
namespace {
use db\Rest as rest;
use util\Pinyin;
function def_hot_opt_rest($app, $br, $dp = array())
{
$aex = $br;
$afr = "{$br}_ext";
$app->get("/hot/{$br}", function () use($aex, $afr) {
$km = get('oid');
$adh = get('pid');
$cj = "select * from `{$aex}` pp join `{$afr}` pv\n              on pp.id = pv.`pid`\n              where pp.oid={$km} and pp.pid={$adh}";
$cp = db::query($cj);
$ab = groupArray($cp, 'name');
$acx = ['Id', 'Oid', 'RowNum'];
$acy = [5, 5, 5];
$cx = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true], ['data' => 'oid', 'renderer' => 'html', 'readOnly' => true], ['data' => '_rownum', 'renderer' => 'html', 'readOnly' => true]];
$ai = [];
foreach ($ab as $bu => $bv) {
$acx[] = $bv[0]['label'];
$acy[] = $bv[0]['col_width'];
$cx[] = ['data' => $bu, 'renderer' => 'html'];
$afs = [];
foreach ($bv as $k => $dk) {
$ai[$dk['_rownum']][$bu] = $dk['option'];
if ($bu == 'value') {
if (!isset($ai[$dk['_rownum']]['id'])) {
$ai[$dk['_rownum']]['id'] = $dk['id'];
$ai[$dk['_rownum']]['oid'] = $km;
$ai[$dk['_rownum']]['_rownum'] = $dk['_rownum'];
}
}
}
}
$ai = array_values($ai);
$v['data'] = ['list' => $ai, 'colHeaders' => $acx, 'colWidths' => $acy, 'cols' => $cx];
ret($v);
});
$app->get("/hot/{$br}_addprop", function () use($aex, $afr) {
$km = get('oid');
$adh = get('pid');
$aft = get('propname');
if ($aft != 'value' && !checkOptPropVal($km, $adh, 'value', $aex, $afr)) {
addOptProp($km, $adh, 'value', $aex, $afr);
}
if (!checkOptPropVal($km, $adh, $aft, $aex, $afr)) {
addOptProp($km, $adh, $aft, $aex, $afr);
}
ret([11]);
});
$app->options("/hot/{$br}", function () {
ret([]);
});
$app->options("/hot/{$br}/{id}", function () {
ret([]);
});
$app->post("/hot/{$br}", function () use($aex, $afr) {
$ab = ctx::data();
$adh = $ab['pid'];
$km = $ab['oid'];
$afu = getArg($ab, '_rownum');
$afv = db::row($aex, ['AND' => ['oid' => $km, 'pid' => $adh, 'name' => 'value']]);
if (!$afv) {
addOptProp($km, $adh, 'value', $aex, $afr);
}
$afw = $afv['id'];
$afx = db::obj()->max($afr, '_rownum', ['pid' => $afw]);
$ab = ['oid' => $km, 'pid' => $afw, '_rownum' => $afx + 1];
db::save($afr, $ab);
$v = ['oid' => $km, '_rownum' => $afu, 'prop' => $afv, 'maxrow' => $afx];
ret($v);
});
$app->put("/hot/{$br}/{id}", function ($z, $bl, $mo) use($afr, $aex) {
$ab = ctx::data();
$adh = $ab['pid'];
$km = $ab['oid'];
$afu = $ab['_rownum'];
$afu = getArg($ab, '_rownum');
$aw = $ab['token'];
$bc = $ab['uid'];
$dk = dissoc($ab, ['oid', 'pid', '_rownum', '_uptm', 'uniqid', 'token', 'uid']);
debug($dk);
$k = key($dk);
$u = $dk[$k];
$afv = db::row($aex, ['AND' => ['pid' => $adh, 'oid' => $km, 'name' => $k]]);
info("{$adh} {$km} {$k}");
$afw = $afv['id'];
$afy = db::obj()->has($afr, ['AND' => ['pid' => $afw, '_rownum' => $afu]]);
if ($afy) {
debug("has cell ...");
$cj = "update {$afr} set `option`='{$u}' where _rownum={$afu} and pid={$afw}";
debug($cj);
db::exec($cj);
} else {
debug("has no cell ...");
$ab = ['oid' => $km, 'pid' => $afw, '_rownum' => $afu, 'option' => $u];
db::save($afr, $ab);
}
$v = ['item' => $dk, 'oid' => $km, '_rownum' => $afu, 'key' => $k, 'val' => $u, 'prop' => $afv, 'sql' => $cj];
ret($v);
});
}
function checkOptPropVal($km, $adh, $br, $aex, $afr)
{
return db::obj()->has($aex, ['AND' => ['name' => $br, 'oid' => $km, 'pid' => $adh]]);
}
function addOptProp($km, $adh, $aft, $aex, $afr)
{
$br = Pinyin::get($aft);
$ab = ['oid' => $km, 'pid' => $adh, 'label' => $aft, 'name' => $br];
$afv = db::save($aex, $ab);
$ab = ['_rownum' => 1, 'oid' => $km, 'pid' => $afv['id']];
db::save($afr, $ab);
return $afv;
}
}
namespace {
log_time("MID BEGIN");
$app->add(new \mid\TwigMid());
$app->add(new \mid\RestMid());
$afz = \cfg::load('mid');
if ($afz) {
foreach ($afz as $bu => $m) {
$agh = "\\{$bu}";
debug("load mid: {$agh}");
$app->add(new $agh());
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