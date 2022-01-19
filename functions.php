<?php
    function buscaAlunosTotal() {
        require("conexaosec.php");
        try {
            $busca = $conn->prepare("SELECT COUNT(*) AS Total FROM pessoas WHERE estado = 0 AND nivel_id != 1 AND nivel_id != 22 AND nivel_id != 18 
            AND nivel_id != 44 AND nivel_id != 129 AND validade_data_fim > GETDATE()");
                                    
            $busca->execute();
    
            $buscaAlunos = $busca->fetch();
            return $buscaAlunos["Total"];
            
        } catch (PDOException $e) {
                die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
        }
    }

    function buscaAlunosSesi() {
        require("conexaosec.php");
        try {
            $buscaSesi = $conn->prepare("SELECT COUNT(*) AS TotalSesi FROM pessoas INNER JOIN niveis ON pessoas.nivel_id = niveis.id WHERE SUBSTRING(niveis.descricao, 1, 1) = 'E' AND estado = 0 AND validade_data_fim > GETDATE()");
                                    
            $buscaSesi->execute();
    
            $buscaAlunosSesi = $buscaSesi->fetch();
            return $buscaAlunosSesi["TotalSesi"];
            
        } catch (PDOException $e) {
                die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
        }
    }

    function buscaAlunosSenai() {
        require("conexaosec.php");
        try {
            $buscaSenai = $conn->prepare("SELECT COUNT(*) AS TotalSenai FROM pessoas INNER JOIN niveis ON pessoas.nivel_id = niveis.id
            WHERE SUBSTRING(niveis.descricao, 1, 1) != 'E' AND estado = 0 AND nivel_id != 1 AND nivel_id != 22 
            AND nivel_id != 18 AND nivel_id != 44 AND nivel_id != 129 AND validade_data_fim > GETDATE()");
                                    
            $buscaSenai->execute();
    
            $buscaAlunosSenai = $buscaSenai->fetch();
            return $buscaAlunosSenai["TotalSenai"];
            
        } catch (PDOException $e) {
                die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
        }
    }

    function buscaDois() {
        require("conexaosec.php");

        $countSesi = 0;
        $countSenai = 0;

        try {
            $buscaDois = $conn->prepare("SELECT * FROM dbo.pessoas AS pessoas INNER JOIN pessoas_niveis AS pniveis ON pessoas.id = pniveis.pessoa_id
            INNER JOIN niveis AS niveis on pniveis.nivel_id = niveis.id WHERE estado = 0 AND pessoas.nivel_id != 1 AND pessoas.nivel_id != 22 
            AND pessoas.nivel_id != 18 AND pessoas.nivel_id != 44 AND pessoas.nivel_id != 129 AND pniveis.nivel_id != 1 AND pniveis.nivel_id != 22
            AND pniveis.nivel_id != 18 AND pniveis.nivel_id != 44 AND pniveis.nivel_id != 129 AND validade_data_fim >= GETDATE()");
                                    
            $buscaDois->execute();
    
            $buscaAlunos = $buscaDois->fetchAll();
            foreach($buscaAlunos as $buscaAlunos) {                
                if(substr($buscaAlunos["descricao"], 0, 1) == 'E') {
                    $countSesi = $countSesi + 1;
                } else {
                    $countSenai = $countSenai + 1;
                }
                
            }

            return $countSesi."/".$countSenai;
            
        } catch (PDOException $e) {
                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
        }
    }

    function porcentagem($alunos, $total) {
        return ($alunos / $total) * 100;
    }

    function buscaQuantTroncos() {
        $server = "192.168.254.14";
        $username = "root";
        $password = "Ses1Sen@1";
        $cmdTroncos = "asterisk -rx ' sip show peers' | grep -w OK | awk '{print $1}' | awk -F '/' '{print $1}'";

        $qtTroncosOnline = 0;
        
        $conn = ssh2_connect($server, 22);
        ssh2_auth_password($conn, $username, $password);
        $stream = ssh2_exec($conn, $cmdTroncos);
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);

        $response = explode("\n", $output);
        foreach($response as $res) {
            if(!empty($res)) {
                $qtTroncosOnline = $qtTroncosOnline + 1;
            }
        }
        return $qtTroncosOnline;
    }

    function buscaQuantRamais() {
        $server = "192.168.254.14";
        $username = "root";
        $password = "Ses1Sen@1";
        $cmdRamais = "asterisk -rx ' pjsip show endpoints' | egrep 'Unavailable|Avail'";

        $qtRamaisOnline = 0;
        
        $conn = ssh2_connect($server, 22);
        ssh2_auth_password($conn, $username, $password);
        $stream = ssh2_exec($conn, $cmdRamais);
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        $output = preg_replace('/ +/', ' ', $output);

        $response = explode("\n", $output);
        foreach($response as $res) {
            $type = substr($res,0,10);
            if($type==" Contact: ") {
                $pos = strpos($res, "/");
                $res = substr($res,10,$pos-10);
                $qtRamaisOnline = $qtRamaisOnline + 1;
            }
        }
        return $qtRamaisOnline;
    }

    function troncosStatus() {
        $server = "192.168.254.14";
        $username = "root";
        $password = "Ses1Sen@1";
        $cmdTroncos = "asterisk -rx ' sip show peers' | grep -w OK | awk '{print $1}' | awk -F '/' '{print $1}'";

        $troncosOnline = array();

        $conn = ssh2_connect($server, 22);
        ssh2_auth_password($conn, $username, $password);
        $stream = ssh2_exec($conn, $cmdTroncos);
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);

        $response = explode("\n", $output);
        foreach($response as $res) {
            if(!empty($res)) {
                array_push($troncosOnline, $res);
            }
        }
            
        return $troncosOnline;
    }

    function ramaisStatus() {
        $server = "192.168.254.14";
        $username = "root";
        $password = "Ses1Sen@1";
        $cmdTroncos = "asterisk -rx ' pjsip show endpoints' | egrep 'Unavailable|Avail'";

        $ramaisOnline = array();
        $ramaisOffline = array();
        $ramaisStatus = array();

        $conn = ssh2_connect($server, 22);
        ssh2_auth_password($conn, $username, $password);
        $stream = ssh2_exec($conn, $cmdTroncos);
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);

        $output = preg_replace('/ +/', ' ', $output);
        $response = explode("\n", $output);
        foreach($response as $res) {
            $type = substr($res,0,10);
            if($type == " Contact: ") {
                $pos = strpos($res,'/');
                $res = substr($res,10,$pos-10);
                array_push($ramaisOnline, $res);
            } else if ($type == " Endpoint:") {
                $pos = strpos($res,'/');
                $res = substr($res,11,$pos-11);
                if($res != "dpma_endpoint Unavailab") {
                    array_push($ramaisOffline, $res);
                }
            }
        }
        
        $ramaisStatus = [
            "online" => $ramaisOnline,
            "offline" => $ramaisOffline
        ];

        return $ramaisStatus;
    }
    
?>