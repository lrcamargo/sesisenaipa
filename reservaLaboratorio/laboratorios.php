<!DOCTYPE html>
    <?php

        error_reporting(E_ALL);
        ini_set('display_errors',1);

        include("../conexao.php");
        session_start();

        if(!isset($_SESSION['sLogin'])){
            header('location:../index.php');
            exit;
        }

        $logado = $_SESSION['user'];
        $nivel = $_SESSION['group'];

        // Normalizar o nível do usuário: lowercase + remover pontos
        $nivelNorm = strtolower(str_replace('.', '', $nivel));

        $lab = $_GET['lab'] ?? 1;

        $stmt = $pdo->prepare("SELECT nome FROM laboratorios WHERE idLaboratorio = ?");
        $stmt->execute([$lab]);
        $laboratorioInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        $nomeLaboratorio = $laboratorioInfo['nome'] ?? 'Laboratório';

        /* LIMITE DE DIAS */

        $limite = 30;

        if($nivelNorm == "sup pedagogica"){
            $limite = 60;
        }

        if($nivelNorm == "sup tecnica" || $nivelNorm == "admin" || $nivelNorm == "administrator"){
            $limite = 3650;
        }

        /* MENSAGENS */

        $erros = [
            1=>"Informe horário de início e fim",
            2=>"Selecione um turno",
            3=>"Já existe reserva nesse horário",
            4=>"Erro ao salvar reserva",
            5=>"Conflito de horário em"
        ];

        $oks = [
            1=>"Reserva criada com sucesso",
            //mensagem completa de recorrentes é montada diretamente no bloco
            2=>"Reservas recorrentes processadas"
        ];

        /* PERMISSÕES */

        $permEvento = in_array($nivelNorm,[
            "sup tecnica",
            "sup pedagogica",
            "gerencia",
            "sup adm",
            "admin",
            "administrator"
        ]);

        $permDiaInteiro = in_array($nivelNorm,[
            "sup tecnica",
            "admin",
            "administrator"
        ]);

        $permPeriodo = in_array($nivelNorm,[
            "sup tecnica",
            "sup pedagogica",
            "gerencia",
            "sup adm",
            "admin",
            "administrator"
        ]);

        $permSolicitante = in_array($nivelNorm,[
            "sup tecnica",
            "sup pedagogica",
            "gerencia",
            "sup adm",
            "admin",
            "administrator"
        ]);

    ?>

    <head>

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laboratórios</title>

        <link rel="stylesheet" href="../../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../../css/telefone.css">
        <link rel='stylesheet' href='../../fullcalendar/main.min.css'/>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">

        <script src='../../fullcalendar/main.min.js'></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>

        <style>
        #previewDatas {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            margin-top: 8px;
        }
        #previewDatas .badge-data {
            display: inline-block;
            background: #007bff;
            color: #fff;
            border-radius: 3px;
            padding: 2px 7px;
            margin: 2px;
            font-size: 0.85em;
        }

        /* [FAIL-SAFE TURMA] Estilos do campo manual de turma */
        .turma-manual-wrapper {
            display: none; /* oculto por padrão — só aparece quando a API falha */
        }
        .turma-manual-wrapper .turma-aviso {
            font-size: 0.82em;
            color: #856404;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 5px 10px;
            margin-bottom: 6px;
        }
        .turma-manual-wrapper input.is-valid   { border-color: #28a745; }
        .turma-manual-wrapper input.is-invalid { border-color: #dc3545; }
        .turma-codigo-feedback {
            font-size: 0.8em;
            margin-top: 3px;
        }
        .turma-codigo-feedback.valido   { color: #28a745; }
        .turma-codigo-feedback.invalido { color: #dc3545; }
        </style>

        <script>

        var laboratorio = <?php echo $lab; ?>;

        document.addEventListener('DOMContentLoaded', function(){

            var calendarEl = document.getElementById('calendar');

            var calendar = new FullCalendar.Calendar(calendarEl, {

                locale: 'pt-br',
                timeZone: 'local',
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                initialView: 'dayGridMonth',

                validRange: function(){
                    var max = new Date();
                    max.setDate(max.getDate() + <?php echo $limite; ?>);
                    return { end: max };
                },

                events: {
                    url: 'listareservas.php?lab=' + laboratorio
                },

                dateClick: function(info){

                    var today = new Date();
                    var date = today.toISOString().split('T')[0];

                    if(info.dateStr < date){
                        alert("Para reservar datas passadas você precisará de um DeLorean.");
                    } else {

                        let array = info.dateStr.split("-");
                        let dataSel = `${array[2]}-${array[1]}-${array[0]}`;

                        $("#reservaModal #data").text(dataSel);
                        document.querySelector('input[name=dataInp]').value = info.dateStr;
                        $("#reservaModal").modal('show');

                        // [FAIL-SAFE TURMA] Usa a função centralizada de carregamento
                        carregarTurmas('turmaSelect', 'turmaManualWrapper', info.dateStr);
                    }
                },

                eventClick: function(info){

                    let inicio = info.event.start.toLocaleTimeString('pt-BR', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    });

                    let fim = info.event.end.toLocaleTimeString('pt-BR', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    });

                    let data        = info.event.start.toLocaleDateString();
                    let turma       = info.event.extendedProps.turma;
                    let solicitante = info.event.extendedProps.solicitante;
                    let descricao   = info.event.extendedProps.descricao;
                    let status      = info.event.extendedProps.status;
                    let statusTexto = status == 1 ? "Aprovado" : "Pendente";

                    document.getElementById("infoData").innerText        = data;
                    document.getElementById("infoInicio").innerText      = inicio;
                    document.getElementById("infoFim").innerText         = fim;
                    document.getElementById("infoTurma").innerText       = turma;
                    document.getElementById("infoSolicitante").innerText = solicitante;
                    document.getElementById("infoDescricao").innerText   = descricao;
                    document.getElementById("infoStatus").innerText      = statusTexto;

                    $("#infoReservaModal").modal('show');
                },

            });

            calendar.render();

            // ================================================================
            // MÁSCARAS EM JS PURO — sem dependência externa
            // ================================================================

            function aplicarMascaraHora(el) {
                el.addEventListener('input', function(e) {
                    var pos    = this.selectionStart;
                    var digits = this.value.replace(/\D/g, '').substring(0, 4);
                    var result = '';

                    if (digits.length >= 1) {
                        var d0 = parseInt(digits[0]);
                        if (d0 > 2) digits = '2' + digits.substring(1);
                        result = digits[0];
                    }
                    if (digits.length >= 2) {
                        var h1 = parseInt(digits[0]);
                        var h2 = parseInt(digits[1]);
                        if (h1 === 2 && h2 > 3) digits = digits[0] + '3' + digits.substring(2);
                        result = digits.substring(0, 2) + ':';
                    }
                    if (digits.length >= 3) {
                        var m1 = parseInt(digits[2]);
                        if (m1 > 5) digits = digits.substring(0, 2) + '5' + digits.substring(3);
                        result = digits.substring(0, 2) + ':' + digits[2];
                    }
                    if (digits.length >= 4) {
                        result = digits.substring(0, 2) + ':' + digits.substring(2, 4);
                    }

                    this.value = result;

                    var novaPos = pos;
                    if (pos === 2 && digits.length >= 2) novaPos = 3;
                    this.setSelectionRange(novaPos, novaPos);
                });

                el.addEventListener('paste', function(e) {
                    e.preventDefault();
                    var texto  = (e.clipboardData || window.clipboardData).getData('text');
                    var digits = texto.replace(/\D/g, '').substring(0, 4);
                    this.value = digits;
                    this.dispatchEvent(new Event('input'));
                });
            }

            function aplicarMascaraData(el, onComplete) {
                el.addEventListener('input', function() {
                    var pos    = this.selectionStart;
                    var digits = this.value.replace(/\D/g, '').substring(0, 8);
                    var result = '';

                    if (digits.length >= 1) {
                        var d0 = parseInt(digits[0]);
                        if (d0 > 3) digits = '0' + digits;
                        result = digits[0];
                    }
                    if (digits.length >= 2) {
                        var dd = parseInt(digits.substring(0, 2));
                        if (dd === 0) digits = '01' + digits.substring(2);
                        if (dd > 31)  digits = '31' + digits.substring(2);
                        result = digits.substring(0, 2) + '/';
                    }
                    if (digits.length >= 3) {
                        var m0 = parseInt(digits[2]);
                        if (m0 > 1) digits = digits.substring(0, 2) + '0' + digits.substring(2);
                        result = digits.substring(0, 2) + '/' + digits[2];
                    }
                    if (digits.length >= 4) {
                        var mm = parseInt(digits.substring(2, 4));
                        if (mm === 0) digits = digits.substring(0, 2) + '01' + digits.substring(4);
                        if (mm > 12)  digits = digits.substring(0, 2) + '12' + digits.substring(4);
                        result = digits.substring(0, 2) + '/' + digits.substring(2, 4) + '/';
                    }
                    if (digits.length >= 5) {
                        result = digits.substring(0, 2) + '/' + digits.substring(2, 4) + '/' + digits.substring(4);
                    }

                    this.value = result;

                    var novaPos = pos;
                    if (pos === 2 && digits.length >= 2) novaPos = 3;
                    if (pos === 5 && digits.length >= 4) novaPos = 6;
                    this.setSelectionRange(novaPos, novaPos);

                    if (result.length === 10 && typeof onComplete === 'function') {
                        var partes = result.split('/');
                        var iso    = partes[2] + '-' + partes[1] + '-' + partes[0];
                        onComplete(iso);
                    } else if (typeof onComplete === 'function') {
                        onComplete('');
                    }
                });

                el.addEventListener('paste', function(e) {
                    e.preventDefault();
                    var texto  = (e.clipboardData || window.clipboardData).getData('text');
                    this.value = texto.replace(/\D/g, '').substring(0, 8);
                    this.dispatchEvent(new Event('input'));
                });
            }

            // --- Máscaras de hora do modal de reserva simples ---
            aplicarMascaraHora(document.getElementById('horainicio'));
            aplicarMascaraHora(document.getElementById('horafim'));

            // --- Modal de reserva por período ---
            if (document.getElementById('modalRecorrente')) {

                document.getElementById('modalRecorrente').addEventListener('show.bs.modal', function() {
                    document.getElementById('previewDatas').innerHTML     = '';
                    document.getElementById('previewDatas').style.display = 'none';
                });

                aplicarMascaraData(
                    document.getElementById('recDataInicioMask'),
                    function(iso) {
                        document.getElementById('recDataInicio').value = iso;
                        if (iso) carregarTurmasRecorrente(iso);
                    }
                );

                aplicarMascaraData(
                    document.getElementById('recDataFimMask'),
                    function(iso) {
                        document.getElementById('recDataFim').value = iso;
                    }
                );

                aplicarMascaraHora(document.getElementById('recHoraInicioMask'));
                document.getElementById('recHoraInicioMask').addEventListener('input', function() {
                    document.getElementById('recHoraInicio').value = this.value;
                });

                aplicarMascaraHora(document.getElementById('recHoraFimMask'));
                document.getElementById('recHoraFimMask').addEventListener('input', function() {
                    document.getElementById('recHoraFim').value = this.value;
                });

                // [FAIL-SAFE TURMA] Validação no campo manual do modal recorrente
                var recManualInput = document.getElementById('recTurmaManualInput');
                if (recManualInput) {
                    recManualInput.addEventListener('input', function() {
                        feedbackCodigoTurma(this, 'recTurmaFeedback');
                    });
                }
            }

            // [FAIL-SAFE TURMA] Validação no campo manual do modal simples
            var turmaManualInput = document.getElementById('turmaManualInput');
            if (turmaManualInput) {
                turmaManualInput.addEventListener('input', function() {
                    feedbackCodigoTurma(this, 'turmaFeedback');
                });
            }

        }); // fim DOMContentLoaded

        // ================================================================
        // [FAIL-SAFE TURMA] — Funções de validação e carregamento
        //
        // Lógica:
        //   1. carregarTurmas() chama a API normalmente
        //   2. Se a API retornar turmas → mostra o select normalmente
        //   3. Se a API falhar ou retornar vazio → oculta o select e
        //      exibe o campo manual com validação de código
        //   4. validarCodigoTurma() identifica o tipo pelo prefixo e
        //      valida o formato com regex específica
        //   5. Antes do submit, o campo correto (select ou manual) é
        //      copiado para o hidden 'turma' que o PHP recebe
        // ================================================================

        /**
         * Regras de validação por tipo de turma.
         * Cada entrada tem: label (nome amigável), regex (padrão esperado),
         * exemplo (mostrado na dica ao usuário).
         *
         * Padrões identificados:
         *   Técnico       HT-MET-01-M-25-13310  → [2L]-[2-4L]-[2N]-[1L]-[2N]-[N+]
         *   Aprendizagem  AI-MEL-08-T-25-13310  → mesma estrutura do técnico
         *   Ensino Médio  EM-1A-A-M-11775-25    → EM-[N][L]-[L]-[L]-[N+]-[2N]
         *   Ens. Fund.    EF-...                → mesma estrutura do EM
         *   Aperfeiçoam.  APP-...               → APP + segmentos alfanum. separados por hífen
         *
         * Regex unificada de fallback: segmentos alfanuméricos separados por hífen
         */
        var TURMA_REGRAS = [
            {
                label:  'Técnico / Aprendizagem',
                // Ex: HT-MET-01-M-25-13310  |  AI-MEL-08-T-25-13310
                // Prefixos conhecidos: HT, AI, MT, EL, ME, etc. (2 letras) + não começa com EM/EF/APP
                prefixo: /^(?!EM|EF|APP)[A-Z]{2}-/,
                regex:   /^[A-Z]{2}-[A-Z]{2,4}-\d{2}-[A-Z]-\d{2}-\d+$/,
                exemplo: 'Ex: HT-MET-01-M-25-13310'
            },
            {
                label:  'Ensino Médio',
                prefixo: /^EM-/,
                regex:   /^EM-\d[A-Z]-[A-Z]-[A-Z]-\d{3,6}-\d{2}$/,
                exemplo: 'Ex: EM-1A-A-M-11775-25'
            },
            {
                label:  'Ensino Fundamental',
                prefixo: /^EF-/,
                regex:   /^EF-\d[A-Z]-[A-Z]-[A-Z]-\d{3,6}-\d{2}$/,
                exemplo: 'Ex: EF-1A-A-M-11775-25'
            },
            {
                label:  'Aperfeiçoamento',
                prefixo: /^APP-/,
                // APP- + pelo menos mais um segmento alfanumérico
                regex:   /^APP(-[A-Z0-9]+){1,}$/,
                exemplo: 'Ex: APP-INF-01-M-25'
            }
        ];

        /**
         * Valida um código de turma digitado manualmente.
         * Retorna { valido: bool, mensagem: string }
         */
        // Valores fixos aceitos literalmente (sem validação de formato)
        // Comparação case-insensitive para aceitar variações de digitação
        var TURMA_VALORES_FIXOS = [
            'Funcionários',
            'Funcionarios',  // sem acento, por segurança
        ];

        function validarCodigoTurma(codigo) {
            var original = codigo.trim();

            if (!original) {
                return { valido: false, mensagem: '' };
            }

            // Verifica valores fixos antes de qualquer transformação
            // Aceita independente de maiúsculas/minúsculas
            for (var f = 0; f < TURMA_VALORES_FIXOS.length; f++) {
                if (original.toLowerCase() === TURMA_VALORES_FIXOS[f].toLowerCase()) {
                    return { valido: true, mensagem: '\u2714 Turma: ' + TURMA_VALORES_FIXOS[f] + '.' };
                }
            }

            // Para os demais casos, normaliza para maiúsculas
            var c = original.toUpperCase();

            // Tenta identificar o tipo pelo prefixo
            for (var i = 0; i < TURMA_REGRAS.length; i++) {
                var regra = TURMA_REGRAS[i];
                if (regra.prefixo.test(c)) {
                    if (regra.regex.test(c)) {
                        return { valido: true, mensagem: '✔ ' + regra.label + ' — código válido.' };
                    } else {
                        return { valido: false, mensagem: '✘ Formato incorreto para ' + regra.label + '. ' + regra.exemplo };
                    }
                }
            }

            // Prefixo não reconhecido — valida estrutura genérica (segmentos alfanum. com hífen)
            // Aceita qualquer código com pelo menos 2 segmentos separados por hífen
            if (/^[A-Z0-9]+(-[A-Z0-9]+){1,}$/.test(c)) {
                return { valido: true, mensagem: '✔ Código aceito (tipo não identificado automaticamente).' };
            }

            return {
                valido: false,
                mensagem: '✘ Código inválido. Use letras maiúsculas e números separados por hífen.'
            };
        }

        /**
         * Atualiza o feedback visual de um campo manual de turma.
         * inputEl  = elemento <input>
         * feedbackId = id do elemento de feedback
         */
        function feedbackCodigoTurma(inputEl, feedbackId) {
            var resultado  = validarCodigoTurma(inputEl.value);
            var feedbackEl = document.getElementById(feedbackId);

            // Força maiúsculas apenas se NÃO for um valor fixo conhecido
            // (ex: 'Funcionários' deve preservar a capitalização original)
            var ehValorFixo = TURMA_VALORES_FIXOS.some(function(v){
                return inputEl.value.trim().toLowerCase() === v.toLowerCase();
            });
            if (!ehValorFixo) {
                inputEl.value = inputEl.value.toUpperCase();
            }

            inputEl.classList.remove('is-valid', 'is-invalid');
            if (feedbackEl) {
                feedbackEl.className = 'turma-codigo-feedback';
                feedbackEl.textContent = resultado.mensagem;
            }

            if (inputEl.value.trim() === '') return;

            if (resultado.valido) {
                inputEl.classList.add('is-valid');
                if (feedbackEl) feedbackEl.classList.add('valido');
            } else {
                inputEl.classList.add('is-invalid');
                if (feedbackEl) feedbackEl.classList.add('invalido');
            }
        }

        /**
         * Carrega turmas via API para um par (selectId, wrapperManualId).
         * Se a API falhar ou retornar vazio, ativa o modo manual.
         *
         * selectId       = id do <select> de turmas
         * wrapperManualId = id do .turma-manual-wrapper
         * dataISO        = data no formato yyyy-mm-dd
         */
        function carregarTurmas(selectId, wrapperManualId, dataISO) {
            var select  = document.getElementById(selectId);
            var wrapper = document.getElementById(wrapperManualId);

            if (!select || !dataISO) return;

            // Reseta para estado de carregamento
            select.innerHTML = '<option value="">Carregando turmas...</option>';
            select.style.display = 'block';
            if (wrapper) wrapper.style.display = 'none';

            fetch('buscarTurmasAPI.php?data=' + dataISO)
                .then(function(response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    // Lê como texto primeiro — evita falha silenciosa de JSON.parse
                    return response.text();
                })
                .then(function(texto) {
                    var data;
                    try {
                        data = JSON.parse(texto);
                    } catch(e) {
                        // Resposta não é JSON válido (ex: HTML de erro PHP)
                        console.warn('[carregarTurmas] Resposta não é JSON:', texto.substring(0, 100));
                        ativarModoManual(select, wrapper, 'API de turmas indisponível.');
                        return;
                    }

                    // Array vazio ou nulo → modo manual
                    if (!data || !Array.isArray(data) || data.length === 0) {
                        ativarModoManual(select, wrapper, 'Nenhuma turma encontrada para esta data.');
                        return;
                    }

                    // API OK — preenche o select normalmente
                    select.innerHTML = '';
                    data.forEach(function(turma) {
                        var option = document.createElement('option');
                        option.value = turma;
                        option.text  = turma;
                        select.appendChild(option);
                    });
                })
                .catch(function(err) {
                    console.warn('[carregarTurmas] Erro no fetch:', err.message);
                    ativarModoManual(select, wrapper, 'API de turmas indisponível.');
                });
        }

        /**
         * Ativa o modo manual de turma:
         * oculta o select, exibe o campo de texto com aviso.
         */
        function ativarModoManual(selectEl, wrapperEl, motivo) {
            selectEl.innerHTML = '';
            selectEl.style.display = 'none';

            if (wrapperEl) {
                // Atualiza o texto do aviso com o motivo real
                var avisoEl = wrapperEl.querySelector('.turma-aviso');
                if (avisoEl) {
                    avisoEl.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i>'
                        + motivo + ' Digite o código da turma manualmente.';
                }
                wrapperEl.style.display = 'block';

                // Limpa estado anterior do campo manual
                var inputManual = wrapperEl.querySelector('input[type=text]');
                if (inputManual) {
                    inputManual.value = '';
                    inputManual.classList.remove('is-valid', 'is-invalid');
                }
                var feedbackEl = wrapperEl.querySelector('.turma-codigo-feedback');
                if (feedbackEl) {
                    feedbackEl.textContent = '';
                    feedbackEl.className = 'turma-codigo-feedback';
                }
            }
        }

        /**
         * Antes de submeter qualquer formulário de reserva,
         * copia o valor correto (select ou campo manual) para
         * o hidden <input name="turma">.
         * Retorna false e impede o submit se o código manual for inválido.
         */
        function prepararSubmitReserva(formId, selectId, wrapperManualId, hiddenTurmaId) {
            var select  = document.getElementById(selectId);
            var wrapper = document.getElementById(wrapperManualId);
            var hidden  = document.getElementById(hiddenTurmaId);

            // Modo API normal: select visível
            if (select && select.style.display !== 'none' && select.value) {
                if (hidden) hidden.value = select.value;
                return true;
            }

            // Modo manual: valida e copia
            if (wrapper && wrapper.style.display !== 'none') {
                var inputManual = wrapper.querySelector('input[type=text]');
                if (!inputManual || !inputManual.value.trim()) {
                    alert('Informe o código da turma.');
                    return false;
                }
                var resultado = validarCodigoTurma(inputManual.value);
                if (!resultado.valido) {
                    alert('Código de turma inválido.\n' + resultado.mensagem);
                    return false;
                }
                // Preserva capitalização original para valores fixos (ex: Funcionários)
                var valorFinal = inputManual.value.trim();
                var ehFixo = TURMA_VALORES_FIXOS.some(function(v){
                    return valorFinal.toLowerCase() === v.toLowerCase();
                });
                if (hidden) hidden.value = ehFixo ? valorFinal : valorFinal.toUpperCase();
                return true;
            }

            alert('Selecione ou informe uma turma.');
            return false;
        }

        // ============================================================
        // FUNÇÕES DO MODAL DE RESERVA POR PERÍODO
        // ============================================================

        function carregarTurmasRecorrente(dataISO) {
            // [FAIL-SAFE TURMA] Usa a função centralizada
            carregarTurmas('recTurmaSelect', 'recTurmaManualWrapper', dataISO);
        }

        function visualizarDatas(){
            var dataInicio = document.getElementById('recDataInicio').value;
            var dataFim    = document.getElementById('recDataFim').value;

            if(!dataInicio || !dataFim){
                alert('Informe a data inicial e final.');
                return;
            }

            if(dataFim < dataInicio){
                alert('A data final deve ser maior ou igual à data inicial.');
                return;
            }

            var diasMarcados = [];
            document.querySelectorAll('input[name="diasSemana[]"]:checked').forEach(function(cb){
                diasMarcados.push(parseInt(cb.value));
            });

            if(diasMarcados.length === 0){
                alert('Selecione ao menos um dia da semana.');
                return;
            }

            var datas = [];
            var atual = new Date(dataInicio + 'T00:00:00');
            var fim   = new Date(dataFim   + 'T00:00:00');

            while(atual <= fim){
                if(diasMarcados.includes(atual.getDay())){
                    var dd   = String(atual.getDate()).padStart(2, '0');
                    var mm   = String(atual.getMonth() + 1).padStart(2, '0');
                    var yyyy = atual.getFullYear();
                    datas.push(dd + '/' + mm + '/' + yyyy);
                }
                atual.setDate(atual.getDate() + 1);
            }

            var container = document.getElementById('previewDatas');
            container.style.display = 'block';

            if(datas.length === 0){
                container.innerHTML = '<span class="text-muted">Nenhuma data encontrada para os filtros selecionados.</span>';
                return;
            }

            container.innerHTML = '<strong>' + datas.length + ' data(s) selecionada(s):</strong><br>' +
                datas.map(function(d){ return '<span class="badge-data">' + d + '</span>'; }).join('');
        }

        </script>

    </head>

<body>

<div class="wrapper">

<div class="header" style="z-index:99">

<div class="header-menu">

<div class="title">
<img src="../../img/logo_white.svg">
</div>

<div class="sidebar-btn">
<i class="fas fa-bars"></i>
</div>

<ul>

<li>
<a href="#" class="user"><?php echo $logado; ?></a>
</li>

<li>
<a href="../sair.php" class="logout">
<i class="fas fa-power-off"></i>
</a>
</li>

</ul>

</div>
</div>

<div class="sidebar">
<div class="sidebar-menu">
<?php include_once('../menu.php'); ?>
</div>
</div>

<div class="main-container">

<?php

if(isset($_GET['erro'])){
    $cod = $_GET['erro'];
    if(isset($erros[$cod])){
        $msg = $erros[$cod];
        if($cod == 5 && isset($_GET['data'])){
            $dataConflito = htmlspecialchars($_GET['data']);
            $msg .= " <strong>" . $dataConflito . "</strong>: já existe uma reserva nesse horário. Nenhuma data foi salva.";
        }
        echo "<div class='alert alert-danger text-center'>" . $msg . "</div>";
    }
}

if(isset($_GET['ok'])){
    $cod = $_GET['ok'];

    if($cod == 1 && isset($oks[1])){
        echo "<div class='alert alert-success text-center'>".$oks[1]."</div>";
    }

    if($cod == 2){
        $inseridas  = intval($_GET['inseridas'] ?? 0);
        $recId      = htmlspecialchars($_GET['rec_id'] ?? '');
        $conflitos  = isset($_GET['conflitos']) && $_GET['conflitos'] !== ''
                      ? explode(',', urldecode($_GET['conflitos']))
                      : [];

        if($inseridas > 0){
            $msgOk  = "<strong>" . $inseridas . " reserva(s) criada(s) com sucesso.</strong>";
            if($recId){
                $msgOk .= " ID do grupo: <strong>" . $recId . "</strong>"
                        . " (use este código para localizar e excluir todas de uma vez).";
            }
            echo "<div class='alert alert-success text-center'>" . $msgOk . "</div>";
        }

        if(!empty($conflitos)){
            $listaHtml = implode(', ', array_map(function($d){
                return '<strong>' . htmlspecialchars(trim($d)) . '</strong>';
            }, $conflitos));
            $msgAviso  = count($conflitos) === 1
                ? "1 data não foi reservada por conflito de horário: " . $listaHtml . "."
                : count($conflitos) . " datas não foram reservadas por conflito de horário: " . $listaHtml . ".";
            echo "<div class='alert alert-warning text-center'>" . $msgAviso . "</div>";
        }

        if($inseridas === 0 && empty($conflitos)){
            echo "<div class='alert alert-warning text-center'>Nenhuma data foi processada. Verifique os dados e tente novamente.</div>";
        }
    }
}

?>

<h3 class="mb-3">
<center><b>Ambiente:</b> <?php echo $nomeLaboratorio; ?></center>
</h3>

<?php if($permPeriodo){ ?>
<div class="mb-3 text-right">
    <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#modalRecorrente">
        <i class="fas fa-calendar-plus"></i> + Reserva por Período
    </button>
</div>
<?php } ?>

<div id='calendar'></div>

</div>

<!-- ======================================================
     MODAL DE RESERVA SIMPLES
     ====================================================== -->
<div class="modal fade" id="reservaModal">
<div class="modal-dialog">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Solicitar Reserva</h5>
<button type="button" class="btn-close" data-dismiss="modal"></button>
</div>

<div class="modal-body">

<!-- [FAIL-SAFE TURMA] onsubmit valida e copia turma para o hidden antes de enviar -->
<form method="POST" action="solicita.php"
      onsubmit="return prepararSubmitReserva('this','turmaSelect','turmaManualWrapper','turmaHidden')">

<b>Data:</b><br>
<span id="data"></span>
<input type="hidden" name="dataInp">

<br><br>

<b>Turno:</b><br>

<input type="radio" name="turno" value="manha"> Manhã
<input type="radio" name="turno" value="tarde"> Tarde
<input type="radio" name="turno" value="noite"> Noite

<?php if($permDiaInteiro){ ?>
<input type="radio" name="turno" value="dia"> Dia inteiro
<?php } ?>

<br><br>

<b>Período:</b><br>

<input type="radio" name="periodo" value="parcial" onChange="horarios()"> Só um período
<input type="radio" name="periodo" value="todo" onChange="horarios()"> Todo o turno

<br><br>

<span name="hInicio" style="display:none"><b>Início</b></span>
<input id="horainicio" name="horainicio" type="text" placeholder="HH:MM" autocomplete="off" style="display:none">

<br>

<span name="hFim" style="display:none"><b>Fim</b></span>
<input id="horafim" name="horafim" type="text" placeholder="HH:MM" autocomplete="off" style="display:none">

<br><br>

<?php if($permEvento){ ?>

<b>Tipo:</b><br>

<select name="tipo">
<option value="aula">Aula</option>
<option value="evento">Evento</option>
</select>

<br><br>

<b>Descrição / Observação:</b>

<textarea name="descricao" class="form-control"></textarea>

<br><br>

<?php } ?>

<b>Turma:</b><br>

<!-- Select normal (modo API) -->
<select id="turmaSelect"></select>

<!-- [FAIL-SAFE TURMA] Campo manual — oculto por padrão, aparece só quando API falha -->
<div id="turmaManualWrapper" class="turma-manual-wrapper mt-1">
    <div class="turma-aviso"></div>
    <input type="text"
           id="turmaManualInput"
           class="form-control form-control-sm"
           placeholder="Ex: HT-MET-01-M-25-13310"
           autocomplete="off"
           maxlength="30">
    <div id="turmaFeedback" class="turma-codigo-feedback"></div>
</div>

<!-- Hidden que o PHP recebe — preenchido pelo prepararSubmitReserva() -->
<input type="hidden" id="turmaHidden" name="turma">

<input type="hidden" name="lab" value="<?php echo $lab; ?>">

<?php

if($permSolicitante){

    echo "<br><br><b>Solicitante:</b><br>";
    echo "<select name='solicitante'>";

    $stmt = $pdo->prepare("SELECT id,nome FROM usuarios ORDER BY nome");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($users as $u){
        echo "<option value='".$u['id']."'>".$u['nome']."</option>";
    }

    echo "</select>";

} else {

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome=?");
    $stmt->execute([$logado]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<input type='hidden' name='solicitante' value='".$user['id']."'>";

}

?>

</div>

<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
<button type="submit" class="btn btn-primary">Solicitar</button>
</div>

</form>

</div>
</div>
</div>

<!-- ======================================================
     MODAL DE INFORMAÇÕES DA RESERVA (sem alterações)
     ====================================================== -->
<div class="modal fade" id="infoReservaModal">
<div class="modal-dialog">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Informações da Reserva</h5>
<button type="button" class="btn-close" data-dismiss="modal"></button>
</div>

<div class="modal-body">

<b>Data:</b>
<div id="infoData"></div>
<br>
<b>Horário:</b>
<div><span id="infoInicio"></span> até <span id="infoFim"></span></div>
<br>
<b>Turma:</b>
<div id="infoTurma"></div>
<br>
<b>Solicitante:</b>
<div id="infoSolicitante"></div>
<br>
<b>Status:</b>
<div id="infoStatus"></div>
<br>
<b>Descrição:</b>
<div id="infoDescricao"></div>

</div>

</div>
</div>
</div>

<!-- ======================================================
     MODAL DE RESERVA POR PERÍODO
     ====================================================== -->
<?php if($permPeriodo){ ?>
<div class="modal fade" id="modalRecorrente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-plus"></i> Reserva por Período
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <!-- [FAIL-SAFE TURMA] onsubmit valida turma do modal recorrente -->
                <form method="POST" action="solicitaRecorrente.php" id="formRecorrente"
                      onsubmit="return prepararSubmitReserva('formRecorrente','recTurmaSelect','recTurmaManualWrapper','recTurmaHidden')">

                    <input type="hidden" name="lab" value="<?php echo $lab; ?>">

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label><b>Data Inicial</b></label>
                                <input type="text"
                                       id="recDataInicioMask"
                                       class="form-control"
                                       placeholder="dd/mm/aaaa"
                                       autocomplete="off"
                                       required>
                                <input type="hidden"
                                       id="recDataInicio"
                                       name="dataInicio">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label><b>Data Final</b></label>
                                <input type="text"
                                       id="recDataFimMask"
                                       class="form-control"
                                       placeholder="dd/mm/aaaa"
                                       autocomplete="off"
                                       required>
                                <input type="hidden"
                                       id="recDataFim"
                                       name="dataFim">
                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <label><b>Hora Início</b></label>
                                <input type="text"
                                       id="recHoraInicioMask"
                                       class="form-control"
                                       placeholder="HH:MM"
                                       autocomplete="off"
                                       required>
                                <input type="hidden"
                                       id="recHoraInicio"
                                       name="horaInicio">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label><b>Hora Fim</b></label>
                                <input type="text"
                                       id="recHoraFimMask"
                                       class="form-control"
                                       placeholder="HH:MM"
                                       autocomplete="off"
                                       required>
                                <input type="hidden"
                                       id="recHoraFim"
                                       name="horaFim">
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label><b>Dias da Semana</b></label><br>
                        <div class="btn-group btn-group-toggle flex-wrap" data-toggle="buttons">
                            <label class="btn btn-outline-secondary btn-sm">
                                <input type="checkbox" name="diasSemana[]" value="1"> Seg
                            </label>
                            <label class="btn btn-outline-secondary btn-sm">
                                <input type="checkbox" name="diasSemana[]" value="2"> Ter
                            </label>
                            <label class="btn btn-outline-secondary btn-sm">
                                <input type="checkbox" name="diasSemana[]" value="3"> Qua
                            </label>
                            <label class="btn btn-outline-secondary btn-sm">
                                <input type="checkbox" name="diasSemana[]" value="4"> Qui
                            </label>
                            <label class="btn btn-outline-secondary btn-sm">
                                <input type="checkbox" name="diasSemana[]" value="5"> Sex
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><b>Turma</b></label>

                        <!-- Select normal (modo API) -->
                        <select id="recTurmaSelect" class="form-control">
                            <option value="">Selecione uma data primeiro</option>
                        </select>

                        <!-- [FAIL-SAFE TURMA] Campo manual do modal recorrente -->
                        <div id="recTurmaManualWrapper" class="turma-manual-wrapper mt-1">
                            <div class="turma-aviso"></div>
                            <input type="text"
                                   id="recTurmaManualInput"
                                   class="form-control form-control-sm"
                                   placeholder="Ex: HT-MET-01-M-25-13310"
                                   autocomplete="off"
                                   maxlength="30">
                            <div id="recTurmaFeedback" class="turma-codigo-feedback"></div>
                        </div>

                        <!-- Hidden que o PHP recebe -->
                        <input type="hidden" id="recTurmaHidden" name="turma">
                    </div>

                    <?php if($permEvento){ ?>
                    <div class="form-group">
                        <label><b>Tipo</b></label>
                        <select name="tipo" class="form-control">
                            <option value="aula">Aula</option>
                            <option value="evento">Evento</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><b>Descrição / Observação</b></label>
                        <textarea name="descricao" class="form-control" rows="2"></textarea>
                    </div>
                    <?php } ?>

                    <?php if($permSolicitante){ ?>
                    <div class="form-group">
                        <label><b>Solicitante</b></label>
                        <select name="solicitante" class="form-control">
                            <?php
                            $stmt2 = $pdo->prepare("SELECT id, nome FROM usuarios ORDER BY nome");
                            $stmt2->execute();
                            $users2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                            foreach($users2 as $u){
                                echo "<option value='".$u['id']."'>".$u['nome']."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <?php } else { ?>
                        <?php
                        $stmtU = $pdo->prepare("SELECT id FROM usuarios WHERE nome=?");
                        $stmtU->execute([$logado]);
                        $userU = $stmtU->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <input type="hidden" name="solicitante" value="<?php echo $userU['id'] ?? ''; ?>">
                    <?php } ?>

                    <div class="form-group mt-3">
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="visualizarDatas()">
                            <i class="fas fa-eye"></i> Visualizar datas
                        </button>
                        <div id="previewDatas" style="display:none"></div>
                    </div>

                </form>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" form="formRecorrente" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Reservas
                </button>
            </div>

        </div>
    </div>
</div>
<?php } ?>

<script>

function horarios(){

    const box = document.querySelector('input[value=parcial]');

    if(box.checked){
        document.querySelector('span[name=hInicio]').style.display     = "block";
        document.querySelector('span[name=hFim]').style.display        = "block";
        document.querySelector('input[name=horainicio]').style.display = "block";
        document.querySelector('input[name=horafim]').style.display    = "block";
    } else {
        document.querySelector('span[name=hInicio]').style.display     = "none";
        document.querySelector('span[name=hFim]').style.display        = "none";
        document.querySelector('input[name=horainicio]').style.display = "none";
        document.querySelector('input[name=horafim]').style.display    = "none";
    }

}

</script>

</body>
</html>