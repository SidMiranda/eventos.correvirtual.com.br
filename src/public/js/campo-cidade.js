/* ---------------------------------------------------------------------------
   Campo de cidade com busca (cadastro e "Minha conta" › Perfil).

   Busca no nosso banco a partir de 3 letras. O texto que a pessoa vê e o
   `city_id` que vai para o banco são campos separados: qualquer digitação
   depois de escolher limpa o id, senão daria para escolher "Mogi Guaçu",
   apagar tudo, escrever outra coisa e enviar o id antigo junto.

   Marcação esperada: #campoCidade (com data-busca = rota cidades.buscar),
   #campoCidadeId e #listaCidades. Ver resources/views/components/campo-cidade.
   --------------------------------------------------------------------------- */
(function () {
    function iniciar() {
        var campo = document.getElementById('campoCidade');
        var campoId = document.getElementById('campoCidadeId');
        var lista = document.getElementById('listaCidades');
        if (!campo || !campoId || !lista) { return; }

        var MINIMO = 3;
        var url = campo.dataset.busca;
        var espera = null;
        var atual = -1;
        var opcoes = [];

        function fechar() {
            lista.hidden = true;
            lista.innerHTML = '';
            atual = -1;
            opcoes = [];
        }

        function escolher(opcao) {
            campo.value = opcao.nome;
            campoId.value = opcao.id;
            fechar();
        }

        function desenhar(cidades) {
            lista.innerHTML = '';
            opcoes = cidades;

            if (!cidades.length) {
                var vazio = document.createElement('li');
                vazio.className = 'cidade-vazia';
                vazio.textContent = 'Nenhuma cidade encontrada.';
                lista.appendChild(vazio);
                lista.hidden = false;
                return;
            }

            cidades.forEach(function (cidade, i) {
                var item = document.createElement('li');
                item.textContent = cidade.nome;
                item.setAttribute('role', 'option');
                item.addEventListener('mousedown', function (e) {
                    /* mousedown e não click: o blur do campo fecharia a lista
                       antes de o clique chegar. */
                    e.preventDefault();
                    escolher(cidade);
                });
                lista.appendChild(item);
                if (i === atual) { item.setAttribute('aria-selected', 'true'); }
            });

            lista.hidden = false;
        }

        function marcar(novo) {
            var itens = lista.querySelectorAll('li[role="option"]');
            if (!itens.length) { return; }
            if (atual >= 0 && itens[atual]) { itens[atual].removeAttribute('aria-selected'); }
            atual = (novo + itens.length) % itens.length;
            itens[atual].setAttribute('aria-selected', 'true');
            itens[atual].scrollIntoView({ block: 'nearest' });
        }

        function buscar() {
            var termo = campo.value.trim();
            if (termo.length < MINIMO) { fechar(); return; }

            fetch(url + '?q=' + encodeURIComponent(termo), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(desenhar)
                .catch(fechar);
        }

        campo.addEventListener('input', function () {
            /* Mexeu no texto, o vínculo anterior deixa de valer. */
            campoId.value = '';
            clearTimeout(espera);
            espera = setTimeout(buscar, 250);
        });

        campo.addEventListener('keydown', function (e) {
            if (lista.hidden) { return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); marcar(atual + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); marcar(atual - 1); }
            else if (e.key === 'Enter' && atual >= 0 && opcoes[atual]) { e.preventDefault(); escolher(opcoes[atual]); }
            else if (e.key === 'Escape') { fechar(); }
        });

        campo.addEventListener('blur', function () { setTimeout(fechar, 120); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
