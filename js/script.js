document.addEventListener('DOMContentLoaded', function () {
    const toggleBtns = document.querySelectorAll('.toggle-reschedule-btn');
    toggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target');
            const box = document.getElementById(targetId);
            if (box) {
                box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
            }
        });
    });

    const form = document.getElementById('reservation-form');
    if (!form) {
        return;
    }

    const inputServiceId = document.getElementById('input-service-id');
    const inputEmployeeId = document.getElementById('input-employee-id');
    const inputDate = document.getElementById('reservation_date');
    const inputStartTime = document.getElementById('input-start-time');

    const step2Section = document.getElementById('step-2-section');
    const step3Section = document.getElementById('step-3-section');
    const step4Section = document.getElementById('step-4-section');
    const step5Section = document.getElementById('step-5-section');

    const ind1 = document.getElementById('ind-step-1');
    const ind2 = document.getElementById('ind-step-2');
    const ind3 = document.getElementById('ind-step-3');
    const ind4 = document.getElementById('ind-step-4');
    const ind5 = document.getElementById('ind-step-5');

    const employeesContainer = document.getElementById('employees-container');
    const upcomingDatesChips = document.getElementById('upcoming-dates-chips');
    const slotsInfoBar = document.getElementById('slots-info-bar');
    const slotsContainer = document.getElementById('slots-container');
    const confirmBtn = document.getElementById('confirm-reservation-btn');

    const sumService = document.getElementById('sum-service');
    const sumEmployee = document.getElementById('sum-employee');
    const sumDate = document.getElementById('sum-date');
    const sumTime = document.getElementById('sum-time');
    const sumPrice = document.getElementById('sum-price');

    let state = {
        serviceId: 0,
        serviceName: '',
        serviceDuration: 0,
        servicePrice: '',
        employeeId: 0,
        employeeName: '',
        upcomingDates: [],
        selectedDate: '',
        selectedStart: '',
        selectedEnd: ''
    };

    function updateIndicators(currentStep) {
        const indicators = [ind1, ind2, ind3, ind4, ind5];
        indicators.forEach(function (el, idx) {
            if (!el) return;
            const stepNum = idx + 1;
            el.classList.remove('active', 'completed');
            if (stepNum < currentStep) {
                el.classList.add('completed');
            } else if (stepNum === currentStep) {
                el.classList.add('active');
            }
        });
    }

    function updateSummary() {
        sumService.textContent = state.serviceName || '—';
        sumEmployee.textContent = state.employeeName || '—';
        sumDate.textContent = state.selectedDate || '—';
        sumTime.textContent = (state.selectedStart && state.selectedEnd)
            ? (state.selectedStart + ' – ' + state.selectedEnd)
            : '—';
        sumPrice.textContent = (state.serviceDuration && state.servicePrice)
            ? (state.serviceDuration + ' min | ' + state.servicePrice + ' zł')
            : '—';

        if (state.serviceId > 0 && state.employeeId > 0 && state.selectedDate && state.selectedStart) {
            step5Section.classList.remove('disabled-step');
            confirmBtn.disabled = false;
            updateIndicators(5);
        } else {
            step5Section.classList.add('disabled-step');
            confirmBtn.disabled = true;
        }
    }

    function resetFromStep(stepNumber) {
        if (stepNumber <= 2) {
            state.employeeId = 0;
            state.employeeName = '';
            state.upcomingDates = [];
            inputEmployeeId.value = '';
            step3Section.classList.add('disabled-step');
            inputDate.disabled = true;
            upcomingDatesChips.innerHTML = '<span class="text-muted" style="font-size:0.85rem;">Wybierz fizjoterapeutę w kroku 2.</span>';
        }
        if (stepNumber <= 3) {
            state.selectedDate = '';
            inputDate.value = '';
            step4Section.classList.add('disabled-step');
            slotsInfoBar.textContent = 'Wybierz datę w kroku 3, aby zobaczyć wolne godziny.';
            slotsContainer.innerHTML = '';
        }
        if (stepNumber <= 4) {
            state.selectedStart = '';
            state.selectedEnd = '';
            inputStartTime.value = '';
        }
        updateSummary();
    }

    function renderUpcomingDateChips(dates) {
        upcomingDatesChips.innerHTML = '';
        if (!dates || dates.length === 0) {
            const info = document.createElement('span');
            info.className = 'text-muted';
            info.style.fontSize = '0.85rem';
            info.textContent = 'Brak zdefiniowanych dyżurów w najbliższych dniach.';
            upcomingDatesChips.appendChild(info);
            return;
        }

        dates.forEach(function (d) {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'date-chip' + (state.selectedDate === d.date ? ' active' : '');
            chip.textContent = d.label + ' (' + d.hours + ')';
            chip.addEventListener('click', function () {
                selectDate(d.date);
            });
            upcomingDatesChips.appendChild(chip);
        });
    }

    function selectDate(dateStr, preselectTime) {
        if (!dateStr || state.employeeId <= 0 || state.serviceId <= 0) {
            return;
        }
        resetFromStep(4);
        state.selectedDate = dateStr;
        inputDate.value = dateStr;

        renderUpcomingDateChips(state.upcomingDates);

        step4Section.classList.remove('disabled-step');
        updateIndicators(4);
        slotsInfoBar.textContent = 'Trwa sprawdzanie dostępnych godzin...';
        slotsContainer.innerHTML = '';

        const url = 'client_panel.php?ajax=slots&service_id=' + encodeURIComponent(state.serviceId)
            + '&employee_id=' + encodeURIComponent(state.employeeId)
            + '&date=' + encodeURIComponent(dateStr);

        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    slotsInfoBar.textContent = data.message || 'Nie udało się pobrać terminów.';
                    return;
                }

                if (!data.shifts || data.shifts.length === 0) {
                    slotsInfoBar.textContent = 'W wybranym dniu (' + dateStr + ') fizjoterapeuta nie ma wyznaczonych godzin pracy. Wybierz inny dzień.';
                    return;
                }

                slotsInfoBar.textContent = 'Godziny pracy fizjoterapeuty w dniu ' + dateStr + ': '
                    + data.shifts.join(', ') + ' (czas zabiegu: ' + data.duration + ' min).';

                if (!data.slots || data.slots.length === 0) {
                    const emptyMsg = document.createElement('p');
                    emptyMsg.className = 'text-muted';
                    emptyMsg.textContent = 'Brak slotów mieszczących czas trwania zabiegu.';
                    slotsContainer.appendChild(emptyMsg);
                    return;
                }

                const legend = document.createElement('div');
                legend.className = 'slot-legend';
                legend.innerHTML = '<span><i class="legend-dot" style="background:#0d9488;"></i>Dostępny termin</span>'
                    + '<span><i class="legend-dot" style="background:#ef4444;"></i>Zajęty / kolizja przedziałów</span>'
                    + '<span><i class="legend-dot" style="background:#94a3b8;"></i>Termin miniony</span>';
                slotsContainer.appendChild(legend);

                const grid = document.createElement('div');
                grid.className = 'slots-grid';

                data.slots.forEach(function (slot) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'slot-btn';

                    if (slot.status === 'available') {
                        btn.textContent = slot.start + ' – ' + slot.end;
                        btn.title = 'Kliknij, aby wybrać godzinę ' + slot.start + ' – ' + slot.end;
                        btn.addEventListener('click', function () {
                            grid.querySelectorAll('.slot-btn').forEach(function (b) {
                                b.classList.remove('selected');
                            });
                            btn.classList.add('selected');
                            state.selectedStart = slot.start;
                            state.selectedEnd = slot.end;
                            inputStartTime.value = slot.start;
                            updateSummary();
                        });

                        if (preselectTime && preselectTime === slot.start) {
                            btn.classList.add('selected');
                            state.selectedStart = slot.start;
                            state.selectedEnd = slot.end;
                            inputStartTime.value = slot.start;
                        }
                    } else if (slot.status === 'conflict') {
                        btn.classList.add('slot-conflict');
                        btn.disabled = true;
                        btn.textContent = slot.start + ' – ' + slot.end;
                        btn.title = slot.reason;
                    } else {
                        btn.classList.add('slot-past');
                        btn.disabled = true;
                        btn.textContent = slot.start + ' – ' + slot.end;
                        btn.title = slot.reason;
                    }

                    grid.appendChild(btn);
                });

                slotsContainer.appendChild(grid);
                updateSummary();
            })
            .catch(function () {
                slotsInfoBar.textContent = 'Wystąpił błąd podczas pobierania dostępnych godzin.';
            });
    }

    function selectEmployee(emp, preselectDate, preselectTime) {
        resetFromStep(3);
        state.employeeId = emp.employee_id;
        state.employeeName = emp.name + ' ' + emp.surname;
        state.upcomingDates = emp.upcoming_dates || [];
        inputEmployeeId.value = emp.employee_id;

        step3Section.classList.remove('disabled-step');
        inputDate.disabled = false;
        updateIndicators(3);

        renderUpcomingDateChips(state.upcomingDates);

        if (preselectDate) {
            selectDate(preselectDate, preselectTime);
        } else if (state.upcomingDates.length > 0) {
            selectDate(state.upcomingDates[0].date);
        }
    }

    function selectService(card, preselectEmpId, preselectDate, preselectTime) {
        document.querySelectorAll('.service-choice').forEach(function (c) {
            c.classList.remove('selected');
        });
        card.classList.add('selected');

        resetFromStep(2);

        state.serviceId = parseInt(card.getAttribute('data-id'), 10) || 0;
        state.serviceName = card.getAttribute('data-name') || '';
        state.serviceDuration = parseInt(card.getAttribute('data-duration'), 10) || 0;
        state.servicePrice = card.getAttribute('data-price') || '';
        inputServiceId.value = state.serviceId;

        step2Section.classList.remove('disabled-step');
        updateIndicators(2);
        employeesContainer.innerHTML = '<p class="text-muted">Ładowanie listy fizjoterapeutów...</p>';

        fetch('client_panel.php?ajax=employees&service_id=' + encodeURIComponent(state.serviceId))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok || !data.employees || data.employees.length === 0) {
                    employeesContainer.innerHTML = '<p class="text-muted">Brak aktywnych fizjoterapeutów przypisanych do tej usługi.</p>';
                    return;
                }

                const grid = document.createElement('div');
                grid.className = 'choice-grid';

                let matchedEmp = null;

                data.employees.forEach(function (emp) {
                    const empCard = document.createElement('div');
                    empCard.className = 'choice-card employee-choice';
                    if (preselectEmpId && emp.employee_id === preselectEmpId) {
                        empCard.classList.add('selected');
                        matchedEmp = { emp: emp, card: empCard };
                    }

                    const title = document.createElement('div');
                    title.className = 'choice-title';
                    title.textContent = '👨‍⚕️ ' + emp.name + ' ' + emp.surname;

                    const desc = document.createElement('div');
                    desc.className = 'choice-sub';
                    desc.textContent = emp.description || 'Fizjoterapeuta';

                    empCard.appendChild(title);
                    empCard.appendChild(desc);

                    empCard.addEventListener('click', function () {
                        grid.querySelectorAll('.employee-choice').forEach(function (c) {
                            c.classList.remove('selected');
                        });
                        empCard.classList.add('selected');
                        selectEmployee(emp);
                    });

                    grid.appendChild(empCard);
                });

                employeesContainer.innerHTML = '';
                employeesContainer.appendChild(grid);

                if (matchedEmp) {
                    selectEmployee(matchedEmp.emp, preselectDate, preselectTime);
                } else if (data.employees.length === 1) {
                    const firstCard = grid.querySelector('.employee-choice');
                    if (firstCard) {
                        firstCard.classList.add('selected');
                        selectEmployee(data.employees[0], preselectDate, preselectTime);
                    }
                }
            })
            .catch(function () {
                employeesContainer.innerHTML = '<p class="text-muted">Błąd podczas pobierania listy pracowników.</p>';
            });
    }

    document.querySelectorAll('.service-choice').forEach(function (card) {
        card.addEventListener('click', function () {
            selectService(card);
        });
    });

    inputDate.addEventListener('change', function () {
        if (inputDate.value) {
            selectDate(inputDate.value);
        }
    });

    const preSrv = parseInt(form.getAttribute('data-preselected-service'), 10) || 0;
    const preEmp = parseInt(form.getAttribute('data-preselected-employee'), 10) || 0;
    const preDate = form.getAttribute('data-preselected-date') || '';
    const preTime = form.getAttribute('data-preselected-time') || '';

    if (preSrv > 0) {
        const preCard = document.querySelector('.service-choice[data-id="' + preSrv + '"]');
        if (preCard) {
            selectService(preCard, preEmp, preDate, preTime);
        }
    }
});
