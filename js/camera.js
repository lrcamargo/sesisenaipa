function loadCamera(){
	var video = document.querySelector("#webCamera");
		//Não remover
		video.setAttribute('autoplay', '');
	    video.setAttribute('muted', '');
		video.setAttribute('playsinline', '');
	    //--
	
	if (navigator.mediaDevices.getUserMedia) {
		navigator.mediaDevices.getUserMedia({audio: false, video: {facingMode: 'environment', 
		width: { min: 354, ideal: 354, max: 354 },
		height: { min: 472, ideal: 472, max: 472 }}})
		.then( function(stream) {
			video.srcObject = stream;
		})
		.catch(function(error) {
			alert("Ooops... Falhou :'(");
		});
	}
}

function takeSnapShot(){
    var video = document.querySelector("#webCamera");
    
	var canvas = document.createElement('canvas');
	canvas.width = video.videoWidth;
	canvas.height = video.videoHeight;
	var ctx = canvas.getContext('2d');
	
	ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
	
	var dataURI = canvas.toDataURL('image/jpeg');
	document.querySelector("#baseImg").value = dataURI;
	var nome = document.getElementById("nomeArquivo").value;
	var turma = document.getElementById("listaTurma").value;
	sendSnapShot(dataURI, nome, turma);
}

function sendSnapShot(base64,nome,turma){	
	var request = new XMLHttpRequest();
		request.open('POST', 'savePhotos.php', true);
		request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		
		request.onload = function() {
			if (request.status >= 200 && request.status < 400) {
				var data = JSON.parse(request.responseText);
				
				if(data.error){
					alert(data.error);
					return false;
				}
				
				document.querySelector("#imagemConvertida").setAttribute("src", data.img);
				document.querySelector("#caminhoImagem a").setAttribute("href", data.img);
				document.querySelector("#caminhoImagem a").innerHTML = data.img.split("/")[1];
			} else {
				alert( "Erro ao salvar. Tipo:" + request.status );
			}
		};
		request.onerror = function() {
		 	alert("Erro ao salvar. Back-End inacessível.");
		}
		
		request.send("baseImg="+base64+"&nome="+nome+"&turma="+turma);
}

loadCamera();

$(document).ready(function() {
    folder_list();
        function folder_list() {
            var action = "fetch";
            $.ajax({
                url: "../camera/functions.php",
                method: "POST",
                data:{action:action},
                success:function(data) {
                    $('#turma').html(data);
                }
            })
        }
    
        $(document).on('click', '#create_folder', function() {
            $('#action').val('create');
            $('#folder_name').val('');
            $('#folder_button').val('Criar');
            $('#old_name').val('');
            $('#change_title').text('Criar Pasta');
            $('#folderModal').modal('show');
        });
        
        $(document).on('click', '#folder_button', function() {
            var folder_name = $('#folder_name').val();
            var action = "create";
            if(folder_name != '') {
                $.ajax({
                    url:"../camera/functions.php",
                    method:"POST",
                    data:{folder_name:folder_name,action:action},
                    success:function(data){
                        alert(data);
                        location.href('192.168.254.16/camera/camera.php');
                        folder_list();
                    }
                });
            } else {
                alert("Entre o nome da pasta");
            }
        })
        
        $(document).on('click', '.delete', function() {
            var file = $('#nomeArquivo').val();
            var folder = $('#listaTurma').val();
            var action = "delete";
            $.ajax({
                    url:"../camera/functions.php",
                    method:"POST",
                    data:{file:file, folder:folder,action:action},
                    success:function(data){
                        alert(data);
                        location.reload();
                    }
                });
        })
    });