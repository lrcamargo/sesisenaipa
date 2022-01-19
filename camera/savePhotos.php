<?php
	if(!isset($_POST['baseImg'])){
		die("{\"error\": \" Ooops... Falhou :'(. Cadê o baseImg?\"}");
	}

	if(!isset($_POST['nome'])){
		die("{\"error\": \" Ooops... Falhou :'(. Cadê o nome\"}");
	}

	if(!isset($_POST['turma'])){
		die("{\"error\": \" Ooops... Falhou :'(. Cadê a turma\"}");
	}
	$result = [];
	$data = str_replace(" ","+",$_POST['baseImg']); 
	
	$name = $_POST['nome'];
	
	if (!file_exists("snaps/2021/".$_POST['turma'])) {
		mkdir("snaps/2021/".$_POST['turma'], 0777, true);
	}
	$path = "snaps/2021/".$_POST['turma']."/{$name}.jpg";

	//data
	$data = explode(',', $data);
	
	//Save data
	file_put_contents($path, base64_decode(trim($data[1])));
	
	//Print Data
	$result['img'] = $path;
	echo json_encode($result, JSON_PRETTY_PRINT);
?>