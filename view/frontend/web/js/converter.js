define([
    'jquery',
    'mage/translate',
    'jquery/ui'
], function ($) {
    'use strict';

    $.widget('magexc.currencyConverter', {
        options: {
            currenciesUrl: '',
            rateUrl: '',
            historyUrl: '',
            defaultFrom: 'USD',
            defaultTo: 'EUR',
            amountDebounceMs: 250
        },

        /**
         * @private
         */
        _create: function () {
            this._currencies = [];
            this._currentRate = null;
            this._activeRange = '1M';
            this._amountTimer = null;
            this._requestSeq = 0;

            this._cacheElements();
            this._bindEvents();
            this._loadCurrencies();
        },

        /**
         * @private
         */
        _cacheElements: function () {
            this.$loading = this.element.find('[data-role="loading"]');
            this.$error = this.element.find('[data-role="error"]');
            this.$panel = this.element.find('[data-role="panel"]');
            this.$amount = this.element.find('[data-role="amount"]');
            this.$from = this.element.find('[data-role="from-select"]');
            this.$to = this.element.find('[data-role="to-select"]');
            this.$swap = this.element.find('[data-role="swap"]');
            this.$convertedAmount = this.element.find('[data-role="converted-amount"]');
            this.$rateLine = this.element.find('[data-role="rate-line"]');
            this.$rateDate = this.element.find('[data-role="rate-date"]');
            this.$rangeButtons = this.element.find('[data-role="range-buttons"] button');
            this.$chart = this.element.find('[data-role="chart"]');
            this.$chartEmpty = this.element.find('[data-role="chart-empty"]');
            this.$chartMin = this.element.find('[data-role="chart-min"]');
            this.$chartMax = this.element.find('[data-role="chart-max"]');
        },

        /**
         * @private
         */
        _bindEvents: function () {
            var self = this;

            this.$from.on('change', function () {
                self._onPairChanged();
            });

            this.$to.on('change', function () {
                self._onPairChanged();
            });

            this.$swap.on('click', function () {
                self._swapCurrencies();
            });

            this.$amount.on('input', function () {
                clearTimeout(self._amountTimer);
                self._amountTimer = setTimeout(function () {
                    self._renderConvertedAmount();
                }, self.options.amountDebounceMs);
            });

            this.$rangeButtons.on('click', function () {
                var range = $(this).data('range');
                self._activeRange = range;
                self.$rangeButtons.removeClass('is-active');
                $(this).addClass('is-active');
                self._loadHistory();
            });
        },

        /**
         * @private
         */
        _loadCurrencies: function () {
            var self = this;

            $.getJSON(this.options.currenciesUrl)
                .done(function (response) {
                    if (!response || !response.success || !response.currencies) {
                        self._showError(
                            (response && response.message) ||
                            $.mage.__('Unable to load the currency list. Please try again later.')
                        );
                        return;
                    }

                    self._currencies = response.currencies.slice().sort(function (a, b) {
                        return a.name.localeCompare(b.name);
                    });

                    self._populateSelect(self.$from, self.options.defaultFrom);
                    self._populateSelect(self.$to, self.options.defaultTo);

                    self.$loading.hide();
                    self.$panel.show();

                    self._onPairChanged();
                })
                .fail(function (jqXHR) {
                    var message = $.mage.__('Unable to load the currency list. Please try again later.');

                    if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        message = jqXHR.responseJSON.message;
                    } else if (jqXHR && jqXHR.status === 404) {
                        message = $.mage.__(
                            'Currency converter endpoint not found (404). Is the module enabled and static content deployed?'
                        );
                    } else if (jqXHR && jqXHR.status === 0) {
                        message = $.mage.__('Could not reach the server. Check your connection and try again.');
                    }

                    self._showError(message);
                });
        },

        /**
         * @private
         */
        _populateSelect: function ($select, preferredCode) {
            var hasPreferred = false;

            $select.empty();

            $.each(this._currencies, function (index, currency) {
                var label = currency.iso_code + ' \u2014 ' + currency.name;
                var $option = $('<option></option>').val(currency.iso_code).text(label);

                if (currency.iso_code === preferredCode) {
                    hasPreferred = true;
                }

                $select.append($option);
            });

            if (hasPreferred) {
                $select.val(preferredCode);
            } else if (this._currencies.length) {
                $select.prop('selectedIndex', 0);
            }
        },

        /**
         * @private
         */
        _swapCurrencies: function () {
            var fromVal = this.$from.val();
            var toVal = this.$to.val();

            this.$from.val(toVal);
            this.$to.val(fromVal);

            this._onPairChanged();
        },

        /**
         * @private
         */
        _onPairChanged: function () {
            if (this.$from.val() === this.$to.val()) {
                this._currentRate = 1;
                this._renderRate(1, this._today());
                this._renderConvertedAmount();
            } else {
                this._loadRate();
            }

            this._loadHistory();
        },

        /**
         * @private
         */
        _loadRate: function () {
            var self = this;
            var seq = ++this._requestSeq;
            var params = {
                base: this.$from.val(),
                quote: this.$to.val()
            };

            this.$rateLine.text($.mage.__('Fetching current rate…'));

            $.getJSON(this.options.rateUrl, params)
                .done(function (response) {
                    if (seq !== self._requestSeq) {
                        return;
                    }

                    if (!response || !response.success || !response.rate) {
                        self._showError($.mage.__('Unable to load the exchange rate. Please try again later.'));
                        return;
                    }

                    self._currentRate = parseFloat(response.rate.rate);
                    self._renderRate(self._currentRate, response.rate.date);
                    self._renderConvertedAmount();
                    self._hideError();
                })
                .fail(function () {
                    if (seq !== self._requestSeq) {
                        return;
                    }
                    self._showError($.mage.__('Unable to load the exchange rate. Please try again later.'));
                });
        },

        /**
         * @private
         */
        _renderRate: function (rate, date) {
            var from = this.$from.val();
            var to = this.$to.val();
            var formattedRate = this._formatNumber(rate, 6);

            this.$rateLine.text('1 ' + from + ' = ' + formattedRate + ' ' + to);
            this.$rateDate.text($.mage.__('As of') + ' ' + date);
        },

        /**
         * @private
         */
        _renderConvertedAmount: function () {
            var amount = parseFloat(this.$amount.val());
            var to = this.$to.val();

            if (isNaN(amount) || this._currentRate === null) {
                this.$convertedAmount.text('\u2014');
                return;
            }

            var converted = amount * this._currentRate;

            this.$convertedAmount.text(this._formatNumber(converted, 2) + ' ' + to);
        },

        /**
         * @private
         */
        _loadHistory: function () {
            var self = this;
            var seq = this._historySeq();
            var params = {
                base: this.$from.val(),
                quote: this.$to.val(),
                range: this._activeRange
            };

            $.getJSON(this.options.historyUrl, params)
                .done(function (response) {
                    if (seq !== self._historyRequestSeq) {
                        return;
                    }

                    if (!response || !response.success || !response.series) {
                        self._renderChart([]);
                        return;
                    }

                    self._renderChart(response.series);
                })
                .fail(function () {
                    if (seq !== self._historyRequestSeq) {
                        return;
                    }
                    self._renderChart([]);
                });
        },

        /**
         * Small helper so the history request has its own sequence counter,
         * independent from the rate request counter, to avoid race conditions
         * when the user changes currencies or ranges quickly.
         *
         * @private
         */
        _historySeq: function () {
            this._historyRequestSeq = (this._historyRequestSeq || 0) + 1;

            return this._historyRequestSeq;
        },

        /**
         * @private
         */
        _renderChart: function (series) {
            var svg = this.$chart.get(0);

            while (svg.firstChild) {
                svg.removeChild(svg.firstChild);
            }

            if (!series || series.length < 2) {
                this.$chartEmpty.show();
                this.$chartMin.text('');
                this.$chartMax.text('');
                return;
            }

            this.$chartEmpty.hide();

            var points = series.map(function (item) {
                return { date: item.date, rate: parseFloat(item.rate) };
            });

            var width = 640;
            var height = 220;
            var paddingX = 8;
            var paddingY = 16;

            var rates = points.map(function (p) {
                return p.rate;
            });
            var minRate = Math.min.apply(null, rates);
            var maxRate = Math.max.apply(null, rates);
            var range = maxRate - minRate || 1;

            var coords = points.map(function (p, index) {
                var x = paddingX + (index / (points.length - 1)) * (width - paddingX * 2);
                var y = height - paddingY - ((p.rate - minRate) / range) * (height - paddingY * 2);

                return { x: x, y: y };
            });

            var svgNs = 'http://www.w3.org/2000/svg';

            var pathData = coords.map(function (c, index) {
                return (index === 0 ? 'M' : 'L') + c.x.toFixed(2) + ',' + c.y.toFixed(2);
            }).join(' ');

            var areaData = pathData +
                ' L' + coords[coords.length - 1].x.toFixed(2) + ',' + (height - paddingY) +
                ' L' + coords[0].x.toFixed(2) + ',' + (height - paddingY) + ' Z';

            var area = document.createElementNS(svgNs, 'path');
            area.setAttribute('d', areaData);
            area.setAttribute('class', 'magexc-cc__chart-area');
            svg.appendChild(area);

            var line = document.createElementNS(svgNs, 'path');
            line.setAttribute('d', pathData);
            line.setAttribute('class', 'magexc-cc__chart-line');
            line.setAttribute('fill', 'none');
            svg.appendChild(line);

            var last = coords[coords.length - 1];
            var dot = document.createElementNS(svgNs, 'circle');
            dot.setAttribute('cx', last.x.toFixed(2));
            dot.setAttribute('cy', last.y.toFixed(2));
            dot.setAttribute('r', 3.5);
            dot.setAttribute('class', 'magexc-cc__chart-dot');
            svg.appendChild(dot);

            var to = this.$to.val();

            this.$chartMin.text($.mage.__('Low') + ': ' + this._formatNumber(minRate, 4) + ' ' + to);
            this.$chartMax.text($.mage.__('High') + ': ' + this._formatNumber(maxRate, 4) + ' ' + to);
        },

        /**
         * @private
         */
        _formatNumber: function (value, decimals) {
            if (isNaN(value)) {
                return '\u2014';
            }

            return Number(value).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: decimals
            });
        },

        /**
         * @private
         */
        _today: function () {
            var d = new Date();

            return d.toISOString().slice(0, 10);
        },

        /**
         * @private
         */
        _showError: function (message) {
            this.$loading.hide();
            this.$error.text(message).show();
        },

        /**
         * @private
         */
        _hideError: function () {
            this.$error.hide();
        }
    });

    return $.magexc.currencyConverter;
});
