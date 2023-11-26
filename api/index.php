
<?php
header('Content-Type: application/json; charset: utf-8');
#require_once 'classes/report.php';
require_once 'classes/acesso.php';

if (isset($_SERVER['HTTP_ORIGIN'])) {
  header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Max-Age: 86400'); 
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
      header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");         
  if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
      header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
}

  class Rest {
    public static function open($requisicao) {
      $url = explode('/', $_REQUEST['url']);
      $classe = ucfirst($url[0]);
      array_shift($url);
      $metodo = $url[0];
      array_shift($url);
      $parametros = array();
      $parametros = $url;

      try {
        if(class_exists($classe)) {
          if(method_exists($classe, $metodo)) {
            $retorno = call_user_func_array(array(new $classe, $metodo), $parametros);
            return json_encode(array('status' => 'sucesso', 'dados' => $retorno));
          } else {
            return json_encode(array('status' => 'error', 'dados' => 'Metodo inexistente'));
          }
        } else {
          return json_encode(array('status' => 'error', 'dados' => 'Classe inexistente'));
        }
      } catch (Exception $e) {
        return json_encode(array('status' => 'error', 'dados' => $e->getMessage()));
      }
   }
  }
  if(isset($_REQUEST)) {
    echo Rest::open($_REQUEST);
  }
?>
