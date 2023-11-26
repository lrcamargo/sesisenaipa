<!DOCTYPE html>
<html>
<head>
    <title>Envio de Arquivos</title>
</head>
<body>
    <h2>Envio de Arquivos</h2>
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
        $uploadDir = "certificados/"; // Diretório onde os arquivos serão salvos
        $uploadedFile = $uploadDir . basename($_FILES["file"]["name"]);
        $uploadOk = true;

        // Verifica se o arquivo já existe
        if (file_exists($uploadedFile)) {
            echo "O arquivo já existe.";
            $uploadOk = false;
        }

        // Verifica o tamanho do arquivo (opcional)
        if ($_FILES["file"]["size"] > 5000000) { // Limite de 5MB
            echo "O arquivo é muito grande.";
            $uploadOk = false;
        }

        // Verifica o tipo de arquivo
        $allowedExtensions = array("pdf", "jpg", "jpeg", "png", "gif");
        $fileExtension = strtolower(pathinfo($uploadedFile, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            echo "Apenas arquivos PDF, JPG, JPEG, PNG e GIF são permitidos.";
            $uploadOk = false;
        }

        if ($uploadOk) {
            if (move_uploaded_file($_FILES["file"]["tmp_name"], $uploadedFile)) {
                echo "O arquivo foi enviado com sucesso e salvo em: " . $uploadedFile;
            } else {
                echo "Ocorreu um erro ao enviar o arquivo.";
            }
        } else {
            echo "O arquivo não foi enviado.";
        }
    }
    ?>

    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data">
        <label for="file">Selecione um arquivo (PDF, JPG, JPEG, PNG, GIF):</label>
        <input type="file" name="file" id="file">
        <input type="submit" name="submit" value="Enviar">
    </form>
</body>
</html>