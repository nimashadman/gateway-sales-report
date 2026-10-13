(function () {
	var monthNames = [
		'فروردین',
		'اردیبهشت',
		'خرداد',
		'تیر',
		'مرداد',
		'شهریور',
		'مهر',
		'آبان',
		'آذر',
		'دی',
		'بهمن',
		'اسفند'
	];
	var weekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

	function toPersianDigits(value) {
		return String(value).replace(/[0-9]/g, function (digit) {
			return '۰۱۲۳۴۵۶۷۸۹'[digit];
		});
	}

	function normalizeDigits(value) {
		return String(value)
			.replace(/[۰-۹]/g, function (digit) {
				return '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit);
			})
			.replace(/[٠-٩]/g, function (digit) {
				return '٠١٢٣٤٥٦٧٨٩'.indexOf(digit);
			});
	}

	function parseJalali(value) {
		var parts = normalizeDigits(value).replace(/-/g, '/').split('/');
		if (parts.length !== 3) {
			return null;
		}

		var year = parseInt(parts[0], 10);
		var month = parseInt(parts[1], 10);
		var day = parseInt(parts[2], 10);

		if (!year || !month || !day || month < 1 || month > 12 || day < 1 || day > 31) {
			return null;
		}

		return { year: year, month: month, day: day };
	}

	function formatJalali(year, month, day) {
		return toPersianDigits(
			String(year).padStart(4, '0') + '/' + String(month).padStart(2, '0') + '/' + String(day).padStart(2, '0')
		);
	}

	function div(a, b) {
		return ~~(a / b);
	}

	function jalaliToGregorian(jy, jm, jd) {
		var days;
		var gy;
		var gdMonths = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
		jy += 1595;
		days = -355668 + 365 * jy + div(jy, 33) * 8 + div((jy % 33) + 3, 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
		gy = 400 * div(days, 146097);
		days %= 146097;
		if (days > 36524) {
			gy += 100 * div(--days, 36524);
			days %= 36524;
			if (days >= 365) {
				days++;
			}
		}
		gy += 4 * div(days, 1461);
		days %= 1461;
		if (days > 365) {
			gy += div(days - 1, 365);
			days = (days - 1) % 365;
		}
		if ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) {
			gdMonths[2] = 29;
		}
		var gm;
		for (gm = 1; gm <= 12 && days >= gdMonths[gm]; gm++) {
			days -= gdMonths[gm];
		}
		return [gy, gm, days + 1];
	}

	function monthLength(year, month) {
		if (month <= 6) {
			return 31;
		}
		if (month <= 11) {
			return 30;
		}
		return ((year + 1) % 4 === 0) ? 30 : 29;
	}

	function firstWeekday(year, month) {
		var gregorian = jalaliToGregorian(year, month, 1);
		var date = new Date(gregorian[0], gregorian[1] - 1, gregorian[2]);
		return (date.getDay() + 1) % 7;
	}

	function closeCalendars() {
		document.querySelectorAll('.pgsr-calendar.is-open').forEach(function (calendar) {
			calendar.classList.remove('is-open');
		});
	}

	function buildCalendar(input) {
		var selected = parseJalali(input.value) || { year: 1403, month: 1, day: 1 };
		var view = { year: selected.year, month: selected.month };
		var calendar = document.createElement('div');
		calendar.className = 'pgsr-calendar';
		input.parentNode.appendChild(calendar);

		function render() {
			var offset = firstWeekday(view.year, view.month);
			var length = monthLength(view.year, view.month);
			var html = '<div class="pgsr-calendar__head">' +
				'<button type="button" class="pgsr-calendar__nav" data-nav="next">›</button>' +
				'<div class="pgsr-calendar__month">' + monthNames[view.month - 1] + ' ' + toPersianDigits(view.year) + '</div>' +
				'<button type="button" class="pgsr-calendar__nav" data-nav="prev">‹</button>' +
				'</div><div class="pgsr-calendar__grid">';

			weekDays.forEach(function (day) {
				html += '<div class="pgsr-calendar__weekday">' + day + '</div>';
			});

			for (var i = 0; i < offset; i++) {
				html += '<button type="button" class="pgsr-calendar__day is-empty" tabindex="-1"></button>';
			}

			for (var dayNumber = 1; dayNumber <= length; dayNumber++) {
				var isSelected = selected.year === view.year && selected.month === view.month && selected.day === dayNumber;
				html += '<button type="button" class="pgsr-calendar__day' + (isSelected ? ' is-selected' : '') + '" data-day="' + dayNumber + '">' + toPersianDigits(dayNumber) + '</button>';
			}

			html += '</div>';
			calendar.innerHTML = html;
		}

		calendar.addEventListener('click', function (event) {
			var target = event.target;
			if (target.dataset.nav === 'next') {
				view.month++;
				if (view.month > 12) {
					view.month = 1;
					view.year++;
				}
				render();
			}
			if (target.dataset.nav === 'prev') {
				view.month--;
				if (view.month < 1) {
					view.month = 12;
					view.year--;
				}
				render();
			}
			if (target.dataset.day) {
				selected = { year: view.year, month: view.month, day: parseInt(target.dataset.day, 10) };
				input.value = formatJalali(selected.year, selected.month, selected.day);
				closeCalendars();
			}
		});

		input.addEventListener('focus', function () {
			var parsed = parseJalali(input.value);
			if (parsed) {
				selected = parsed;
				view = { year: parsed.year, month: parsed.month };
			}
			render();
			closeCalendars();
			calendar.classList.add('is-open');
		});

		render();
	}

	document.querySelectorAll('.pgsr-jalali-input').forEach(function (input) {
		buildCalendar(input);
		input.addEventListener('input', function () {
			var value = normalizeDigits(input.value).replace(/[^\d/-]/g, '').replace(/-/g, '/');
			var parts = value.split('/').map(function (part) {
				return part.slice(0, 4);
			});

			if (parts[1]) {
				parts[1] = parts[1].slice(0, 2);
			}
			if (parts[2]) {
				parts[2] = parts[2].slice(0, 2);
			}

			input.value = toPersianDigits(parts.slice(0, 3).join('/'));
		});
	});

	document.addEventListener('click', function (event) {
		if (!event.target.closest('.pgsr-field')) {
			closeCalendars();
		}
	});
})();
