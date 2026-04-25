Repositório para arquivos da intranet na escola SESI/SENAI Pouso Alegre.

## Versão intranet: 3.2
- Gestão de turmas: férias, aperfeiçoamento.
- Monitor IoT - integração com portas e ar (leitura de status e dados).
- Monitor IoT - envio da planta da escola e visualização.

## Versão intranet: 3.1
- Leitura de horário do Excel - painel de gestão de instrutores e meus horários.
- Monitor IoT - integração com portas e ar (leitura de status e dados).

## Versão intranet: 2.3
- Reorganização dos arquivos de tela principal.
- Corrigido redirecionamento de login
- Alterado layout da tela de troca de senha
- Adicionada função de deploy remoto manual para desenvolvimento em ambiente próprio.
- Adicionada função para checar versão de deploy agendado.
- Cadastro de ambientes
- Troca do logo
- Quem - onde está *Integração com reservas de laboratórios*
- Listar eventos no teatro na interface principal da secretaria
- Gestão de Usuários - Mudança no sistema de login - criação de novo sistema de gestão de usuários internos:
    - Adicionada função de resetar senha do usuário
    - Corrigida edição de usuário que precisava digitar os dados
    - Adicionando cadastro vinculado a catraca - caso não esteja online deixa uma lista em json para cadastro automatizado depois.
- Reserva de Laboratórios:
    - Melhoria layout. 
    - Adicionada opção de selecionar dois turnos para supervisão pedagógica,técnica e admin.
    - Edição/aprovação/cancelamento de reservas: adicionada opção de cancelar reserva própria e de reprovar uma reserva pela supervisão, além de restaurar uma reserva reprovada.
    - Adicionado nome do ambiente no botão da reserva - buscar ambientes para reserva apenas que são laboratório (não estoque).
    - Paginação das reservas.
- Gestão de ambientes e turmas:
    - Cadastro de ambientes
    - Cadastro de turmas
    - Mapeamento de turma x sala
    - Painel de ocupação interno e externo = quem/onde está?

## Versão intranet: 2.2
- Removido gerador de carteirinhas
- Modificado sistema de login - sai do AD e passa para banco de dados local.
- Criada interface de gestão de usuários para gestores.
- Atualizado sistema de reservas de laboratórios
    - Mudança de prazo de reserva para supervisão pedagógica e técnica.
    - Liberada reserva recorrente para supervisão técnica.
    - Alterada interface principal de laboratórios e reservas.
    - Busca de turmas da catraca nova.
    - Integrados todos os laboratórios em apenas uma interface.
    - Alterações gerais de layout.

## Versão intranet: 2.1.1
- Gerador de carteirinhas está com erro devido a mudanças no servidor. Para geração genérica de funcionários foi removida a busca pela foto.
- Adicionada página teste do botão de update do github.

## Versão intranet: 2.1
- Removidos alguns recursos atualmente desnecessários que estavam em desenvolvimento.
- Iniciando desenvolvimento de novo sistema de login - listar usuários, base de dados inicial, ativar/desativar usuários e iniciando tela de login e criação de perfis de acesso.

Recursos da versão: 
- Controle horário sirene;
- Registro horários;
- Busca dados base da catraca - dashboard com número de alunos
- Gerador de carteirinhas de acesso (tira fotos e importa automaticamente no sistema).
- Dados energia
- TFTP telefone
- Login externo - alunos
- Agenda laboratório v1.3

## Versão intranet: 2.0
Recursos da versão: 
- Controle horário sirene;
- Registro horários;
- Busca dados base da catraca - dashboard com número de alunos
- Gerador de carteirinhas de acesso (tira fotos e importa automaticamente no sistema).
- Dados energia
- TFTP telefone
- Login externo - alunos
- Agenda laboratório v1.3 - 

## Em desenvolvimento (update 19/04/2026):
- Monitoramento e interface de monitor/controle IoT.
- Atualização de layout - sistema sirene
- Gestão de estoque - cadastro de produtos
- Atualizar sistema diretamente pelo GitHub e automaticamente
- Integrar/criar botão centralizado para todos os sistemas internos *catraca, ar, portas*
- Monitoramento dos dispositivos IoT.
- Serivço para monitoramento de IoT automático.
- Script para cadastrar na catraca os usuários de catraca offline.
- Criação de turmas de aperfeiçoamento manualmente.
- Cadastro de férias de turmas e instrutores.

## A desenvolver (update 02/04/2026):
- Deploy automático por script às madrugadas.
- Sistema de login: envio de e-mail automático na criação de usuário e na alteração de senha.
- Reserva de laboratórios: envio de e-mail para reprovações ou reservas feitas pela supervisão; Adicionar aprovação automática; Fila de e-mails de comunicados; API para node-red; apagar registros antigos na base de histórico (cancelados/reprovados).

