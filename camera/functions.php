<?php
if(isset($_POST['action'])) {
    if($_POST['action'] == "fetch") {
        $folder = array_filter(glob('snap/2021/*'),'is_dir');
        $output = '
            <select id="listaTurma" class="listaTurma" name="listaTurma" id="listaTurma">
        ';

        if(count($folder) > 0) {
            foreach($folder as $name) {
                $output .= '
                        <option value="'.substr($name,10).'">'.substr($name,10).'</option>
                ';
            }
        } else {
            $output .= '
                <option value="">Nenhuma turma encontrada</option>
                ';
        }
        $output .= '</select>';
        echo $output;
    }

    if($_POST["action"] == "create") {
        if(!file_exists($_POST["folder_name"])) {
            mkdir("snap/2021/".$_POST["folder_name"], 077, true);
            echo "Pasta criada";
        } else {
            echo 'Pasta já existe';
        }
    }

    if($_POST["action"] == "delete") {
        if(file_exists("snap/2021/".$_POST["folder"]."/".$_POST["file"]).".jpg") {
            unlink("snap/2021/".$_POST["folder"]."/".$_POST["file"].".jpg");
            echo "Arquivo removido.";
        } else {
            echo 'Arquivo não existe.';
        }
    }
}
?>
