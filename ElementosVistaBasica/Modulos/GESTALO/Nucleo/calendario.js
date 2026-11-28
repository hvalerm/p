(function () {
    'use strict';

    const calendario = document.getElementById('calendario');
    if (!calendario) return;

    const reservas = JSON.parse(calendario.dataset.reservas);
    const hoy = calendario.dataset.hoy;
    const dias = document.getElementById('dias');
    const mesActual = document.getElementById('mes-actual');
    const anterior = document.getElementById('mes-anterior');
    const siguiente = document.getElementById('mes-siguiente');
    const seleccion = document.getElementById('seleccion');
    const mensaje = document.getElementById('mensaje');
    const partesHoy = hoy.split('-').map(Number);
    let anio = partesHoy[0];
    let mes = partesHoy[1] - 1;
    let inicio = null;
    let fin = null;
    const formatoMes = new Intl.DateTimeFormat('es', { month: 'long', year: 'numeric', timeZone: 'UTC' });
    const formatoDia = new Intl.DateTimeFormat('es', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });

    function fechaISO(ano, numeroMes, dia) {
        return ano + '-' + String(numeroMes + 1).padStart(2, '0') + '-' + String(dia).padStart(2, '0');
    }

    function fechaLegible(fecha) {
        const partes = fecha.split('-').map(Number);
        return formatoDia.format(new Date(Date.UTC(partes[0], partes[1] - 1, partes[2])));
    }

    function reservada(fecha) {
        return reservas.some(reserva => reserva.inicio <= fecha && fecha < reserva.fin);
    }

    function mostrarMensaje(texto) {
        mensaje.textContent = texto;
        mensaje.hidden = texto === '';
    }

    function elegir(fecha) {
        mostrarMensaje('');
        if (inicio === null || fin !== null) {
            if (reservada(fecha)) {
                mostrarMensaje('Esa noche está reservada. Elige otra fecha de check-in.');
                return;
            }
            inicio = fecha;
            fin = null;
        } else {
            if (fecha <= inicio) {
                mostrarMensaje('El check-out debe ser posterior al check-in.');
                return;
            }
            if (reservas.some(reserva => inicio < reserva.fin && fecha > reserva.inicio)) {
                mostrarMensaje('El rango seleccionado incluye noches reservadas.');
                return;
            }
            fin = fecha;
        }
        dibujar();
    }

    function dibujar() {
        dias.replaceChildren();
        const primerDia = new Date(Date.UTC(anio, mes, 1));
        const huecos = (primerDia.getUTCDay() + 6) % 7;
        const cantidad = new Date(Date.UTC(anio, mes + 1, 0)).getUTCDate();
        mesActual.textContent = formatoMes.format(primerDia);
        anterior.disabled = anio === partesHoy[0] && mes === partesHoy[1] - 1;
        for (let i = 0; i < huecos; i++) {
            const vacio = document.createElement('span');
            vacio.setAttribute('aria-hidden', 'true');
            dias.append(vacio);
        }
        for (let numero = 1; numero <= cantidad; numero++) {
            const fecha = fechaISO(anio, mes, numero);
            const ocupada = reservada(fecha);
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.textContent = numero;
            boton.disabled = fecha < hoy;
            boton.classList.toggle('reservado', ocupada);
            boton.classList.toggle('rango', inicio !== null && fin !== null && fecha >= inicio && fecha <= fin);
            boton.classList.toggle('extremo', fecha === inicio || fecha === fin);
            boton.setAttribute('aria-pressed', String(fecha === inicio || fecha === fin));
            boton.setAttribute('aria-label', fechaLegible(fecha) + (ocupada ? ', noche reservada' : ', noche disponible'));
            boton.addEventListener('click', () => elegir(fecha));
            dias.append(boton);
        }
        seleccion.textContent = inicio === null
            ? 'Selecciona la fecha de check-in.'
            : fin === null
                ? 'Check-in: ' + fechaLegible(inicio) + '. Selecciona el check-out.'
                : 'Check-in: ' + fechaLegible(inicio) + ' · Check-out: ' + fechaLegible(fin);
    }

    anterior.addEventListener('click', function () {
        mes--;
        if (mes < 0) { mes = 11; anio--; }
        mostrarMensaje('');
        dibujar();
    });
    siguiente.addEventListener('click', function () {
        mes++;
        if (mes > 11) { mes = 0; anio++; }
        mostrarMensaje('');
        dibujar();
    });
    document.getElementById('limpiar').addEventListener('click', function () {
        inicio = null;
        fin = null;
        mostrarMensaje('');
        dibujar();
    });
    dibujar();
}());
