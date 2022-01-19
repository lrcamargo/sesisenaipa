<?php
if(isset($_POST['action'])) {
    if($_POST['action'] == "fetch") {
        $folder = array_filter(glob('snaps/2021/*'),'is_dir');
        $output = '
            <select id="listaTurma" class="listaTurma" name="listaTurma" id="listaTurma">
        ';

        if(count($folder) > 0) {
            foreach($folder as $name) {
                $output .= '
                        <option value="'.substr($name,11).'">'.substr($name,11).'</option>
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
            mkdir("snaps/2021/".$_POST["folder_name"], 077, true);
            echo "Pasta criada";
        } else {
            echo 'Pasta já existe';
        }
    }

    if($_POST["action"] == "delete") {
        if(file_exists("snaps/2021/".$_POST["folder"]."/".$_POST["file"]).".jpg") {
            unlink("snaps/2021/".$_POST["folder"]."/".$_POST["file"].".jpg");
            echo "Arquivo removido.";
        } else {
            echo 'Arquivo não existe.';
        }
    }
}
?>