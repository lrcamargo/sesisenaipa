$(document).ready(function() {

    // Restaura o estado salvo APÓS o DOM estar pronto e renderizado,
    // usando requestAnimationFrame para garantir que o browser já
    // calculou o layout antes de adicionar a classe collapse
    if (localStorage.getItem('menuCollapsed') === '1') {
        requestAnimationFrame(function() {
            $(".wrapper").addClass("collapse");
        });
    }

    // Toggle ao clicar no hambúrguer + salva o novo estado
    $(".sidebar-btn").click(function() {
        $(".wrapper").toggleClass("collapse");
        var collapsed = $(".wrapper").hasClass("collapse");
        localStorage.setItem('menuCollapsed', collapsed ? '1' : '0');
    });

});