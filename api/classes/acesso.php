<?php
	class acesso {
		public function libera($parametros) {
			
			if(strlen($parametros) <= 8) {
				$cartao = $parametros;
			} else {
				$cartao = substr($parametros,0,-1);
			}
			include("conexaoFechadura.php");
			try {
				$busca = $conn->prepare("SELECT * FROM funcionarios WHERE tag = ?");
				$busca->bindValue(1,$cartao);
				$busca->execute();

				$resultados = array();

				$dados = $busca->fetchAll();
				foreach($dados as $dados) {
					#$resultados[] = $dados;
					$date = date('Y-m-d H:i:s');
					//if(!empty($dados['tag']) && $dados['portaoSesi'] == 1) {
					if($dados['tag'] > 0 && $dados['portaoSesi'] == 1) {
						$resultado = "Liberado";
						$registra = $conn->prepare("INSERT INTO registroAcessos (registro,local,dataHora,info) VALUES (?,?,?,'Liberado')");
						/*$registra->bindValue(1,$dados['registro']);
						$registra->bindValue(2,"31");
						$registra->bindValue(3,$date);
						$registra->execute();*/
					} else {
						$resultado = "Bloqueado";
						/*$registra = $conn->prepare("INSERT INTO registroAcessos (registro,local,dataHora,info) VALUES (?,?,?,'Bloqueado')");
						$registra->bindValue(1,$dados['registro']);
						$registra->bindValue(2,"31");
						$registra->bindValue(3,$date);
						$registra->execute();*/
						return $resultados;
					}
				}
			} catch(PDOException $e) {
				throw new Exception($e->getMessage());
			}
			if($resultado != "Liberado") {
				$resultados = "Bloqueado";
				/*$registra = $conn->prepare("INSERT INTO registroAcessos (registro,local,dataHora,info) VALUES (?,?,?,'Bloqueado')");
				$registra->bindValue(1,$dados['registro']);
				$registra->bindValue(2,"31");
				$registra->bindValue(3,$date);
				$registra->execute();*/
				return $resultados;
			} else {
				$resultados = $resultado;
				return $resultados;	
			}
		}}
?>
