/* RENACE: modo claro u oscuro, ver la clave, vuelto del cobro y menú en celular. Sin librerías. */
(function () {
    'use strict';

    const raiz = document.documentElement;

    document.querySelectorAll('[data-tema]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const nuevo = raiz.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            const aplicar = function () { raiz.setAttribute('data-bs-theme', nuevo); };
            // El navegador funde la vista vieja con la nueva; si no lo soporta, cambia directo.
            if (document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.startViewTransition(aplicar);
            } else {
                aplicar();
            }
            try { localStorage.setItem('renace-tema', nuevo); } catch (e) { /* sin almacenamiento */ }
        });
    });

    // Menú de las tres barras: se cierra al tocar fuera o con Escape.
    const botonMenu = document.querySelector('[data-menu]');
    if (botonMenu) {
        const panel = document.getElementById(botonMenu.getAttribute('aria-controls'));
        const mostrar = function (abierto) {
            panel.hidden = !abierto;
            botonMenu.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            botonMenu.setAttribute('aria-label', abierto ? 'Cerrar el menú' : 'Abrir el menú');
        };
        botonMenu.addEventListener('click', function () { mostrar(panel.hidden); });
        document.addEventListener('click', function (evento) {
            if (!panel.hidden && !evento.target.closest('.menu-cuenta')) { mostrar(false); }
        });
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !panel.hidden) {
                mostrar(false);
                botonMenu.focus();
            }
        });
    }

    // Departamentos de la barra: cada botón abre su lista de módulos. Uno abierto a la vez; se cierran
    // al tocar fuera o con Escape. Con el teclado: flecha abajo abre y entra a la lista, y las flechas
    // recorren sus opciones.
    // Solo los botones de la barra: las opciones de un <select data-filtra> también usan data-grupo.
    const botonesGrupo = Array.from(document.querySelectorAll('button.grupo-boton[data-grupo]'));
    const panelDe = function (boton) { return document.getElementById(boton.getAttribute('aria-controls')); };
    const abrirGrupo = function (boton, abierto) {
        panelDe(boton).hidden = !abierto;
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    };
    const cerrarGrupos = function (excepto) {
        botonesGrupo.forEach(function (b) { if (b !== excepto && b.getAttribute('aria-expanded') === 'true') { abrirGrupo(b, false); } });
    };
    botonesGrupo.forEach(function (boton) {
        const panel = panelDe(boton);
        const opciones = function () { return Array.from(panel.querySelectorAll('a')); };
        boton.addEventListener('click', function () {
            const abrir = panel.hidden;
            cerrarGrupos(boton);
            abrirGrupo(boton, abrir);
        });
        boton.addEventListener('keydown', function (evento) {
            if (evento.key === 'ArrowDown') {
                evento.preventDefault();
                cerrarGrupos(boton);
                abrirGrupo(boton, true);
                opciones()[0].focus();
            }
        });
        panel.addEventListener('keydown', function (evento) {
            const lista = opciones();
            const i = lista.indexOf(document.activeElement);
            if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
                evento.preventDefault();
                const paso = evento.key === 'ArrowDown' ? 1 : -1;
                lista[(i + paso + lista.length) % lista.length].focus();
            }
        });
    });
    document.addEventListener('click', function (evento) {
        if (!evento.target.closest('.grupo-menu')) { cerrarGrupos(null); }
    });
    document.addEventListener('keydown', function (evento) {
        const abierto = botonesGrupo.find(function (b) { return b.getAttribute('aria-expanded') === 'true'; });
        if (evento.key === 'Escape' && abierto) {
            abrirGrupo(abierto, false);
            abierto.focus();
        }
    });

    // Campana de avisos: la lista se pide al servidor la primera vez que se abre, así no
    // agrega una consulta a cada página. Se cierra al tocar fuera o con Escape.
    const botonAvisos = document.querySelector('[data-avisos]');
    if (botonAvisos) {
        const panelAvisos = document.getElementById(botonAvisos.getAttribute('aria-controls'));
        let cargado = false;
        const mostrarAvisos = function (abierto) {
            panelAvisos.hidden = !abierto;
            botonAvisos.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            if (abierto && !cargado) {
                cargado = true;
                fetch('/avisos/pendientes', { credentials: 'same-origin' })
                    .then(function (r) { if (!r.ok) { throw new Error(String(r.status)); } return r.text(); })
                    .then(function (html) { panelAvisos.innerHTML = html; })
                    .catch(function () {
                        cargado = false;
                        panelAvisos.innerHTML = '<p class="muted avisos-vacio">No se pudieron cargar los avisos. <a href="/bitacoras?tab=avisos">Ver la pestaña de avisos</a></p>';
                    });
            }
        };
        botonAvisos.addEventListener('click', function () { mostrarAvisos(panelAvisos.hidden); });
        document.addEventListener('click', function (evento) {
            if (!panelAvisos.hidden && !evento.target.closest('.menu-avisos')) { mostrarAvisos(false); }
        });
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !panelAvisos.hidden) { mostrarAvisos(false); botonAvisos.focus(); }
        });
    }

    // Ventanas emergentes (ficha del empleado, editar puesto): se abren con data-abrir="id".
    document.querySelectorAll('[data-abrir]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const ventana = document.getElementById(boton.dataset.abrir);
            const ver = ventana.querySelector('.ficha-ver');
            if (ver) {
                ver.hidden = false;
                ventana.querySelector('.ficha-editar').hidden = true;
            }
            ventana.showModal();
        });
    });
    document.querySelectorAll('dialog.ficha').forEach(function (ventana) {
        const ver = ventana.querySelector('.ficha-ver');
        const editar = ventana.querySelector('.ficha-editar');
        // Tocar fuera del recuadro también la cierra.
        ventana.addEventListener('click', function (evento) {
            if (evento.target === ventana) { ventana.close(); }
        });
        ventana.querySelectorAll('[data-cerrar]').forEach(function (b) { b.addEventListener('click', function () { ventana.close(); }); });
        ventana.querySelectorAll('[data-editar]').forEach(function (b) {
            b.addEventListener('click', function () {
                ver.hidden = true;
                editar.hidden = false;
                editar.querySelector('input:not([type=hidden])').focus();
            });
        });
        ventana.querySelectorAll('[data-ver]').forEach(function (b) {
            b.addEventListener('click', function () {
                editar.hidden = true;
                ver.hidden = false;
            });
        });
    });

    // Paginación y filtros de listas, sin ir a la base de datos.
    // <table data-paginar> pagina sus filas; un contenedor con data-paginar=".selector" pagina esos elementos.
    // <th data-filtrar> agrega un filtro con los valores de esa columna; data-buscar="no" quita el buscador
    // (para pantallas que ya buscan en el servidor).
    const sinTildes = function (texto) {
        return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    };
    const crear = function (etiqueta, clase, texto) {
        const el = document.createElement(etiqueta);
        if (clase) { el.className = clase; }
        if (texto !== undefined) { el.textContent = texto; }
        return el;
    };
    document.querySelectorAll('[data-paginar]').forEach(function (lista) {
        const esTabla = lista.tagName === 'TABLE';
        const filas = Array.from(esTabla ? lista.tBodies[0].rows : lista.querySelectorAll(lista.dataset.paginar));
        if (filas.length <= 5) { return; }
        const textos = filas.map(function (f) { return sinTildes(f.textContent); });
        const opciones = [10, 25, 50, 100];
        let porPagina = 10;
        let pagina = 1;

        // Barra superior: buscador, filtros por columna y filas por página.
        const barra = crear('div', 'tabla-barra');
        let buscador = null;
        if (lista.dataset.buscar !== 'no') {
            buscador = crear('input', 'form-control buscar');
            buscador.type = 'search';
            buscador.placeholder = 'Filtrar esta lista';
            buscador.setAttribute('aria-label', 'Filtrar esta lista');
            barra.appendChild(buscador);
        }
        const filtros = [];
        if (esTabla) {
            lista.querySelectorAll('thead th[data-filtrar]').forEach(function (th) {
                const columna = th.cellIndex;
                const valores = Array.from(new Set(filas.map(function (f) {
                    return f.cells[columna] ? f.cells[columna].textContent.trim() : '';
                }).filter(Boolean))).sort(function (a, b) { return a.localeCompare(b, 'es'); });
                if (valores.length < 2) { return; }
                const select = crear('select', 'form-select');
                select.setAttribute('aria-label', 'Filtrar por ' + th.textContent.trim());
                select.appendChild(new Option(th.textContent.trim() + ': todos', ''));
                valores.forEach(function (v) { select.appendChild(new Option(v, v)); });
                barra.appendChild(select);
                filtros.push({ columna: columna, select: select });
            });
        }
        const tamano = crear('select', 'form-select por-pagina');
        tamano.setAttribute('aria-label', 'Filas por página');
        opciones.forEach(function (n) { tamano.appendChild(new Option(n + ' por página', String(n))); });
        barra.appendChild(tamano);

        // Pie: resumen y páginas.
        const pie = crear('div', 'tabla-pie');
        const resumen = crear('span', 'resumen');
        resumen.setAttribute('aria-live', 'polite');
        const nav = crear('nav', 'paginas');
        nav.setAttribute('aria-label', 'Páginas');
        pie.appendChild(resumen);
        pie.appendChild(nav);

        const envoltura = esTabla ? (lista.closest('.table-responsive') || lista) : null;
        if (esTabla) {
            envoltura.before(barra);
            envoltura.after(pie);
        } else {
            filas[0].before(barra);
            filas[filas.length - 1].after(pie);
        }

        const visibles = function () {
            const busqueda = buscador ? sinTildes(buscador.value) : '';
            return filas.filter(function (fila, i) {
                if (busqueda !== '' && !textos[i].includes(busqueda)) { return false; }
                return filtros.every(function (f) {
                    return f.select.value === '' || (fila.cells[f.columna] && fila.cells[f.columna].textContent.trim() === f.select.value);
                });
            });
        };
        const boton = function (texto, destino, etiqueta, actual, desactivado) {
            const b = crear('button', '', texto);
            b.type = 'button';
            b.disabled = desactivado;
            if (etiqueta) { b.setAttribute('aria-label', etiqueta); }
            if (actual) { b.setAttribute('aria-current', 'page'); }
            b.addEventListener('click', function () { pagina = destino; pintar(); });
            return b;
        };
        const pintar = function () {
            const lasVisibles = visibles();
            const total = Math.max(1, Math.ceil(lasVisibles.length / porPagina));
            pagina = Math.min(Math.max(1, pagina), total);
            const desde = (pagina - 1) * porPagina;
            filas.forEach(function (f) { f.hidden = true; });
            lasVisibles.slice(desde, desde + porPagina).forEach(function (f) { f.hidden = false; });
            resumen.textContent = lasVisibles.length === 0
                ? 'Ningún resultado con ese filtro.'
                : 'Mostrando ' + (desde + 1) + '–' + Math.min(desde + porPagina, lasVisibles.length) + ' de ' + lasVisibles.length;

            nav.replaceChildren();
            nav.hidden = total === 1;
            if (total === 1) { return; }
            nav.appendChild(boton('‹', pagina - 1, 'Página anterior', false, pagina === 1));
            // Primera, última y las vecinas de la actual; los saltos se marcan con "…".
            let anterior = 0;
            for (let n = 1; n <= total; n++) {
                if (n === 1 || n === total || Math.abs(n - pagina) <= 1) {
                    if (n - anterior > 1) { nav.appendChild(crear('span', 'salto-puntos', '…')); }
                    nav.appendChild(boton(String(n), n, 'Página ' + n, n === pagina, false));
                    anterior = n;
                }
            }
            nav.appendChild(boton('›', pagina + 1, 'Página siguiente', false, pagina === total));
            const ir = crear('label', 'ir-a', 'Ir a ');
            const numero = crear('input', 'form-control');
            numero.type = 'number';
            numero.min = '1';
            numero.max = String(total);
            numero.value = String(pagina);
            numero.setAttribute('aria-label', 'Número de página, de 1 a ' + total);
            numero.addEventListener('change', function () { pagina = Number(numero.value) || 1; pintar(); });
            numero.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); pagina = Number(numero.value) || 1; pintar(); }
            });
            ir.appendChild(numero);
            ir.appendChild(document.createTextNode(' de ' + total));
            nav.appendChild(ir);
        };

        if (buscador) { buscador.addEventListener('input', function () { pagina = 1; pintar(); }); }
        filtros.forEach(function (f) { f.select.addEventListener('change', function () { pagina = 1; pintar(); }); });
        tamano.addEventListener('change', function () { porPagina = Number(tamano.value); pagina = 1; pintar(); });

        // Si hay una fila elegida (paciente abierto, referencia del rastro), arranca en su página.
        const elegida = filas.findIndex(function (f) { return f.classList.contains('on') || f.classList.contains('sel'); });
        if (elegida >= 0) { pagina = Math.floor(elegida / porPagina) + 1; }
        pintar();
    });

    // Documento: el ejemplo del campo cambia con el tipo elegido.
    const ejemplos = { CEDULA: '1-1111-1111', DIMEX: '155812345678', PASAPORTE: 'B12345678' };
    document.querySelectorAll('select[data-documento]').forEach(function (tipo) {
        const campo = document.getElementById(tipo.dataset.documento);
        if (!campo) { return; }
        const actualizar = function () {
            campo.placeholder = ejemplos[tipo.value] || '';
            campo.inputMode = tipo.value === 'PASAPORTE' ? 'text' : 'numeric';
        };
        tipo.addEventListener('change', actualizar);
        actualizar();
    });

    // Fecha y hora de Costa Rica, siempre al día.
    const reloj = document.querySelector('[data-reloj]');
    if (reloj) {
        const formato = new Intl.DateTimeFormat('es-CR', {
            timeZone: 'America/Costa_Rica', weekday: 'long', day: 'numeric', month: 'long',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        });
        // Sin segundos: un número que cambia cada segundo distrae y no le sirve a nadie.
        const pintarHora = function () {
            const f = {};
            formato.formatToParts(new Date()).forEach(function (parte) { f[parte.type] = parte.value; });
            reloj.textContent = f.weekday.charAt(0).toUpperCase() + f.weekday.slice(1) + ' ' + f.day + ' de ' + f.month
                + ', ' + f.hour + ':' + f.minute;
        };
        pintarHora();
        setInterval(pintarHora, 15000);
    }

    // Nuevo pedido: una fila por producto, tantas como hagan falta.
    const lineas = document.querySelector('[data-lineas]');
    if (lineas) {
        const numerar = function () {
            const filas = lineas.querySelectorAll('[data-linea]');
            filas.forEach(function (fila, i) {
                const n = i + 1;
                const etiquetas = fila.querySelectorAll('label');
                etiquetas[0].textContent = 'Producto ' + n;
                etiquetas[0].htmlFor = fila.querySelector('select').id = 'producto' + n;
                etiquetas[1].htmlFor = fila.querySelector('input').id = 'cantidad' + n;
                fila.querySelector('[data-quitar-linea]').hidden = filas.length === 1;
            });
        };
        document.querySelector('[data-agregar-linea]').addEventListener('click', function () {
            const fila = lineas.querySelector('[data-linea]').cloneNode(true);
            fila.querySelector('select').value = '';
            fila.querySelector('input').value = '';
            fila.querySelector('input').required = false;
            lineas.appendChild(fila);
            numerar();
            fila.querySelector('select').focus();
        });
        lineas.addEventListener('click', function (evento) {
            const quitar = evento.target.closest('[data-quitar-linea]');
            if (quitar) {
                quitar.closest('[data-linea]').remove();
                numerar();
            }
        });
        // Al elegir un producto la cantidad queda en 1, para que no se pierda por ir vacía.
        lineas.addEventListener('change', function (evento) {
            if (evento.target.tagName === 'SELECT') {
                const cantidad = evento.target.closest('[data-linea]').querySelector('input');
                cantidad.required = evento.target.value !== '';
                if (cantidad.required && cantidad.value === '') { cantidad.value = '1'; }
            }
        });
        numerar();
    }

    document.querySelectorAll('[data-ver-clave]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const campo = document.getElementById(boton.dataset.verClave);
            const ver = campo.type === 'password';
            campo.type = ver ? 'text' : 'password';
            boton.setAttribute('aria-label', ver ? 'Ocultar clave' : 'Mostrar clave');
            boton.querySelector('i').className = ver ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // Las acciones delicadas de administración piden la clave antes de enviarse.
    const dialogo = document.getElementById('confirmar-clave');
    if (dialogo) {
        const clave = document.getElementById('confirmar-clave-valor');
        const aviso = document.getElementById('confirmar-aviso');
        let formulario = null;

        document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
            form.addEventListener('submit', function (evento) {
                const campo = form.dataset.confirmarCampo;
                if (campo && (form.elements[campo].value !== form.dataset.confirmarValor
                    || form.dataset.confirmarActual === form.dataset.confirmarValor)) {
                    return;
                }
                evento.preventDefault();
                formulario = form;
                document.getElementById('confirmar-titulo').textContent = form.dataset.confirmar;
                clave.value = '';
                aviso.hidden = true;
                dialogo.showModal();
                clave.focus();
            });
        });

        dialogo.querySelector('[data-confirmar-cancelar]').addEventListener('click', function () {
            dialogo.close();
        });

        const aceptar = function () {
            if (clave.value === '') {
                aviso.hidden = false;
                return;
            }
            const oculto = document.createElement('input');
            oculto.type = 'hidden';
            oculto.name = 'clave_confirmacion';
            oculto.value = clave.value;
            formulario.appendChild(oculto);
            dialogo.close();
            formulario.submit();
        };
        dialogo.querySelector('[data-confirmar-aceptar]').addEventListener('click', aceptar);
        clave.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                aceptar();
            }
        });
    }

    const recibido = document.querySelector('[data-recibido]');
    const vuelto = document.querySelector('[data-vuelto]');
    if (recibido && vuelto) {
        const total = Number(recibido.dataset.recibido);
        const actualizar = function () {
            const monto = Number(recibido.value || 0);
            vuelto.value = '₡' + Math.max(0, monto - total).toLocaleString('es-CR', { minimumFractionDigits: 2 });
        };
        recibido.addEventListener('input', actualizar);
        actualizar();
    }

    // Opciones que muestran un grupo de campos y ocultan los demás (data-alternar="id del fieldset").
    // Los campos ocultos quedan deshabilitados: ni se validan ni se envían.
    document.querySelectorAll('input[data-alternar]').forEach(function (opcion) {
        opcion.addEventListener('change', function () {
            document.querySelectorAll('input[name="' + opcion.name + '"][data-alternar]').forEach(function (otra) {
                const grupo = document.getElementById(otra.dataset.alternar);
                grupo.hidden = !otra.checked;
                grupo.disabled = !otra.checked;
            });
        });
    });

    // Un select con data-filtra="id de otro select" deja ver en el otro solo las opciones de su grupo
    // (data-grupo). Si la opción elegida queda fuera, se borra la elección.
    document.querySelectorAll('select[data-filtra]').forEach(function (filtro) {
        const destino = document.getElementById(filtro.dataset.filtra);
        if (!destino) { return; }
        const aplicar = function () {
            Array.from(destino.options).forEach(function (o) {
                const fuera = filtro.value !== '' && o.dataset.grupo !== undefined && o.dataset.grupo !== filtro.value;
                o.hidden = fuera;
                o.disabled = fuera;
            });
            if (destino.selectedOptions[0] && destino.selectedOptions[0].disabled) { destino.value = ''; }
        };
        filtro.addEventListener('change', aplicar);
        aplicar();
    });

    // Un formulario se envía una sola vez: un doble clic (o volver a tocar mientras la página carga)
    // repetía la operación; al cambiar la clave, el segundo envío ya no reconocía la clave vieja.
    document.addEventListener('submit', function (evento) {
        const form = evento.target;
        if (evento.defaultPrevented || form.method.toLowerCase() !== 'post') {
            return;
        }
        if (form.dataset.enviando === '1') {
            evento.preventDefault();
            return;
        }
        form.dataset.enviando = '1';
        // Después de este turno, para que el botón presionado alcance a enviar su valor.
        setTimeout(function () {
            form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (b) { b.disabled = true; });
        }, 0);
    });
    // Al volver con el botón Atrás, el navegador puede mostrar la página guardada con los botones apagados.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-enviando]').forEach(function (form) {
            delete form.dataset.enviando;
            form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (b) { b.disabled = false; });
        });
    });
})();
