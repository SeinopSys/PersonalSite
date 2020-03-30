(function ($) {
    'use strict';

    let $tabs = $('#main-tabs'),
        hashchange = function (hash) {
            if (hash && /^#[a-z-]+$/.test(hash)) {

                $('.tab-panel').addClass('d-none');
                const $linkedTab = $(hash);
                const $tabPills = $tabs.find('a').removeClass('active');
                if ($linkedTab.length) {
                    $linkedTab.removeClass('d-none');
                    $tabPills.filter(function () {
                        return this.hash === hash;
                    }).addClass('active');
                } else $('.tab-panel.not-found').removeClass('d-none').show();
            } else {
                let $a = $tabs.find('a').first().addClass('active');
                $($a.attr('href')).removeClass('d-none');
            }
        };
    $w.on('hashchange', function () {
        hashchange(window.location.hash);
    }).triggerHandler('hashchange');

    $('.tab-panel form').on('clear', e => {
        $(e.target).find('.form-control[required], .form-control[pattern]').each((_, el) => {
            $(el).trigger('change');
        });
    });

    $('.form-control[required], .form-control[pattern]').on('keydown keyup change', e => {
        const $target = $(e.target);
        let isValid;
        if (e.target.tagName === 'INPUT')
            isValid = $target.is(':valid');
        else {
            if (typeof $target.attr('required') !== 'undefined')
                isValid = $target.val().length > 0;
            else isValid = true;

            if (isValid) {
                const pattern = $target.attr('pattern');
                if (typeof pattern !== 'undefined') {
                    isValid = new RegExp(pattern).test($target.val());
                }
            }
        }
        $target[isValid ? 'removeClass' : 'addClass']('is-invalid');
    }).trigger('change');

    let octetPad = bin => $.pad(bin, '0', 8),
        blockPad = bin => $.pad(bin, '0', 16),
        ipPad = (bin, dir, ipv6 = false, chr = '0') => $.pad(bin, chr, ipv6 ? 128 : 32, dir),
        ipv6trim = ipv6 => ipv6.replace(/(^|:)0(:0)*:0(:|$)/, '::'),
        decbin = (dec, separator, ipv6 = false) => {
            let binary = [];
            if (typeof dec === 'string')
                dec = dec.split(separator);
            $.each(dec, (_, el) => {
                binary.push((ipv6 ? blockPad : octetPad)(parseInt(el, 10).toString(2)));
            });
            return binary.join(separator);
        },
        bindec = bin => {
            let dec = [];
            if (typeof bin === 'number')
                bin = bin.toString(2);
            bin = bin.indexOf('.') === -1 ? bin.match(/.{8}/g) : bin.split('.');
            $.each(bin, (_, el) => {
                dec.push(parseInt(el, 2));
            });
            return dec.join('.');
        },
        binhex = bin => {
            let hex = [];
            if (typeof bin === 'number')
                bin = bin.toString(2);
            bin = bin.indexOf(':') === -1 ? bin.match(/.{16}/g) : bin.split(':');
            $.each(bin, (_, el) => {
                hex.push(parseInt(el, 2).toString(16));
            });
            return ipv6trim(hex.join(':'));
        },
        ipversion = cidr => {
            const parts = cidr.split('/');
            if (parts.length > 2)
                return false;
            if (/^[\d.]+$/.test(parts[0]))
                return '4';
            if (/^[\da-f:]+$/i.test(parts[0]))
                return '6';
            return false;
        },
        fitsIntoPowerOf2 = n => Math.pow(2, Math.ceil(Math.log(n) / Math.log(2)));

    if (typeof Math.log2 !== 'function')
        Math.log2 = n => Math.log(n) * Math.LOG2E;

    class ValidationError {
        constructor(string) {
            this.message = string;
            this.name = 'ValidationError';
        }
    }

    class BinaryIncrementable {
        /**
         * @param {int} start
         * @param {int} length
         * @param {int} by
         */
        incrRange(start, length, by) {
            if (typeof by !== 'number')
                by = 1;

            let address = this.getBinary(),
                range = address.substring(start, start + length),
                incremented = $.pad((parseInt(range, 2) + by).toString(2), '0', length);
            if (incremented.length > length)
                throw new Error('Incremented length exceeds available space');
            address = address.substring(0, start) + incremented + address.substring(start + Math.max(length, incremented.length));
            this.setBinary(address);

            return this;
        }

        getBinary() {
            throw new Error('Method must be implemented by extending class');
        }

        setBinary() {
            throw new Error('Method must be implemented by extending class');
        }

        /**
         * https://stackoverflow.com/a/42306236/1344955
         *
         * @param {string} amount
         * @return {string}
         */
        addBinary(amount) {
            const original = this.getBinary();
            if (typeof amount !== 'string')
                amount = amount.toString(2);
            if (amount.length < original.length)
                amount = $.pad(amount, '0', original.length, $.pad.left);
            let i = original.length - 1,
                j = amount.length - 1,
                carry = 0,
                result = '';
            //jshint -W016
            while (i >= 0 || j >= 0) {
                let m = i < 0 ? 0 : original[i] | 0,
                    n = j < 0 ? 0 : amount[j] | 0;
                carry += m + n; // sum of two digits
                result = carry % 2 + result; // string concat
                carry = carry / 2 | 0; // remove decimals,  1 / 2 = 0.5, only get 0
                i--;
                j--;
            }
            if (carry !== 0)
                result = carry + result;

            this.setBinary(result);
            return this;
        }
    }

    class IPV4Address extends BinaryIncrementable {
        /**
         * @param {string} dotdec
         */
        constructor(dotdec) {
            super();
            this.parts = dotdec.split('.');
            if (this.parts.length !== 4)
                throw new ValidationError(
                    $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipadd_format_invalid, {dotdec})
                );
            $.each(this.parts, (i, el) => {
                let part = parseInt(el, 10);
                if (isNaN(part))
                    throw new ValidationError(
                        $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipadd_octet_invalid, {
                            dotdec: dotdec,
                            n: i + 1,
                            why: Laravel.jsLocales.vlsm_error_ipadd_octet_invalid_nan,
                        })
                    );
                if (part > 255 || part < 0)
                    throw new ValidationError(
                        $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipadd_octet_invalid, {
                            dotdec: dotdec,
                            n: i + 1,
                            why: Laravel.jsLocales.vlsm_error_ipadd_octet_invalid_range,
                        })
                    );
                this.parts[i] = part;
            });
        }

        /**
         * @param {Netmask} mask
         */
        networkAddress(mask) {
            let ipbin = this.getBinary(),
                maskbin = mask.getBinary(),
                netbin = '';

            for (let i = 0, l = maskbin.length; i < l; i++)
                netbin += maskbin[i] === '1' ? ipbin[i] : maskbin[i];

            return new IPV4Address(bindec(netbin));
        }

        toString() {
            return this.parts.join('.');
        }

        getBinary(dotted = false) {
            return decbin(this.parts, dotted ? '.' : '');
        }

        setBinary(bin, dotted = false) {
            if (dotted)
                bin = bin.replace(/\./g, '');
            this.constructor(bindec(bin));
            return this;
        }

        static fromBinary(bin, dotted = false) {
            if (dotted)
                bin = bin.replace(/\./g, '');
            return new IPV4Address(bindec(bin));
        }
    }

    class IPV6AddressBlock {
        constructor(value = 0) {
            this.value = value;
        }

        toString(pad = false) {
            let val = this.value.toString(16).toLowerCase();
            if (pad)
                val = $.pad(val, '0', 4);
            return val;
        }
    }

    class IPV6Address extends BinaryIncrementable {
        constructor(colonhex) {
            super();
            // Check for shortening symbol
            if (colonhex.indexOf('::') !== -1) {
                let sections = colonhex.split('::');
                if (sections.length > 2)
                    throw new ValidationError(
                        $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipv6add_short_invalid, {colonhex})
                    );

                // :: is at the beginning
                if (sections[0].length === 0) {
                    // The string ends after :: it's an empty adress
                    if (sections[1].length === 0) {
                        this.blocks = $.strRepeat('0', 8).split('').map(() => new IPV6AddressBlock());
                    }
                    // We have stuff after :: so parse it
                    else {
                        const blocks = sections[1].split(':');
                        this.blocks = [];
                        $.each(blocks, (n, el) => {
                            this.blocks.push(IPV6Address.parseBlock(el, colonhex, n));
                        });
                        // Fill the beginning with zeroes
                        if (this.blocks.length < 8) {
                            let prepend = [];
                            for (let i = 0; i < 8 - this.blocks.length; i++)
                                prepend.push(new IPV6AddressBlock());
                            this.blocks.splice.apply(this.blocks, [0, 0].concat(prepend));
                        } else if (this.blocks.length > 8)
                            throw new ValidationError(
                                $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipv6add_too_many_blocks, {colonhex})
                            );
                    }
                }
                // We have stuff before ::
                else {
                    const blocks = sections[0].split(':');
                    this.blocks = [];
                    $.each(blocks, (n, el) => {
                        this.blocks.push(IPV6Address.parseBlock(el, colonhex, n));
                    });
                    // String ends after ::
                    if (sections[1].length === 0) {
                        // Fill the end with zeroes
                        if (this.blocks.length < 8)
                            while (this.blocks.length < 8)
                                this.blocks.push(new IPV6AddressBlock());
                        else if (this.blocks.length > 8)
                            throw new ValidationError(
                                $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipv6add_too_many_blocks, {colonhex})
                            );
                    }
                    // There's more data after ::
                    else {
                        const moreblocks = sections[1].split(':');
                        let tmpparts = [];
                        $.each(moreblocks, (n, el) => {
                            tmpparts.push(IPV6Address.parseBlock(el, colonhex, n));
                        });

                        const partsum = this.blocks.length + tmpparts.length;
                        if (partsum > 8)
                            throw new ValidationError(
                                $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipv6add_too_many_blocks, {colonhex})
                            );
                        if (partsum !== 8)
                            for (let i = 0; i < 8 - partsum; i++)
                                this.blocks.push(new IPV6AddressBlock());
                        this.blocks.splice.apply(this.blocks, [this.blocks.length, 0].concat(tmpparts));
                    }
                }
            }
            // No shortening, parse normally
            else {
                let blocks = colonhex.split(':');
                if (blocks.length > 8)
                    throw new ValidationError(
                        $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipv6add_too_many_blocks, {colonhex})
                    );
                this.blocks = [];
                $.each(blocks, (n, el) => {
                    this.blocks.push(IPV6Address.parseBlock(el, colonhex, n));
                });
            }
        }

        static parseBlock(block, colonhex, n) {
            const value = parseInt(block, 16);

            if (isNaN(value) || !/^[a-f\d]{1,4}$/i.test(block)) {
                throw new ValidationError(
                    $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipv6add_block_invalid, {
                        colonhex,
                        n,
                        why: Laravel.jsLocales.vlsm_error_ipv6add_block_invalid_nan,
                    })
                );
            } else if (value < 0 || value > 0xFFFF)
                throw new ValidationError(
                    $.translatePlaceholders(Laravel.jsLocales.vlsm_error_ipv6add_block_invalid, {
                        colonhex,
                        n,
                        why: Laravel.jsLocales.vlsm_error_ipv6add_block_invalid_range,
                    })
                );

            return new IPV6AddressBlock(value);
        }

        toString() {
            return ipv6trim(this.blocks.join(':'));
        }

        getBinary(coloned = false) {
            return decbin(this.blocks.map(el => el.value), coloned ? ':' : '', true);
        }

        setBinary(bin, coloned = false) {
            if (coloned)
                bin = bin.replace(/:/g, '');
            this.constructor(binhex(bin));
            return this;
        }

        static fromBinary(bin, coloned = false) {
            if (coloned)
                bin = bin.replace(/:/g, '');
            return new IPV6Address(binhex(bin));
        }

        /**
         * @param {Netmask} mask
         */
        networkAddress(mask) {
            let ipbin = this.getBinary(),
                maskbin = mask.getBinary(),
                netbin = '';

            for (let i = 0, l = maskbin.length; i < l; i++)
                netbin += maskbin[i] === '1' ? ipbin[i] : maskbin[i];

            return new IPV6Address(binhex(netbin));
        }
    }

    class Netmask {
        constructor(length, skipValidation = false, ipv6 = false) {
            this.ipv6 = ipv6;
            this.length = parseInt(length, 10);
            if (!skipValidation) {
                const max = ipv6 ? 128 : 32;
                const min = 0;
                if (this.length > max || this.length < min)
                    throw new ValidationError(
                        $.translatePlaceholders(Laravel.jsLocales.vlsm_error_mask_length_invalid, {max, min})
                    );
            }
        }

        toString() {
            return this.length.toString();
        }

        getDecimal() {
            return (this.ipv6 ? binhex : bindec)(this.getBinary());
        }

        getReverseDecimal() {
            return (this.ipv6 ? binhex : bindec)(this.getReverseBinary());
        }

        /**
         * Returns an easy to understand abbreviation based on length
         * Multiples of 8 and 30 are returned as numbers
         * Any other length is returned without preceeding 255 octets
         */
        getAbbrev() {
            if (this.ipv6 || this.length === 30 || this.length % 8 === 0)
                return this.length;

            return this.getDecimal().replace(/^(?:255(\.))+/, '$1');
        }

        static _separateBinary(binary) {
            return binary.match(new RegExp('.{' + (this.ipv6 ? 16 : 8) + '}', 'g')).join(this.ipv6 ? ':' : '.');
        }

        getBinary(withSeparators = false) {
            let binary = ipPad(Array(this.length + 1).join('1'), $.pad.right, this.ipv6);
            if (withSeparators)
                binary = Netmask._separateBinary(binary);
            return binary;
        }

        getReverseBinary(withSeparators = false) {
            let binary = ipPad(Array(this.length + 1).join('0'), $.pad.right, this.ipv6, '1');
            if (withSeparators)
                binary = Netmask._separateBinary(binary);
            return binary;
        }
    }

    class Network {
        constructor(cidr) {
            let _split = cidr.split('/');
            const
                ip = _split[0].trim(),
                ipv6 = ipversion(cidr) === '6';
            this.mask = new Netmask(_split[1].trim(), false, ipv6);
            this.ip = (ipv6 ? new IPV6Address(ip) : new IPV4Address(ip)).networkAddress(this.mask);
        }

        toString() {
            return this.ip + '/' + this.mask;
        }

        setMask(to, ipv6 = false) {
            this.mask = new Netmask(to, false, ipv6);
            this.ip = this.ip.networkAddress(this.mask);

            return this;
        }
    }

    class Subnet {
        constructor(line, ipv6) {
            let _split = line.trim().replace(/(?:^|\s+)\/\/.*$/, '');
            if (_split.length === 0) {
                this.empty = true;
                return;
            }
            _split = _split.match(/^\s*(.*)\s+(\d+|\/\d{1,3})(?:\s+(gép|eszköz|ips?|pcs?|devices?|ger[aä]te?))?\s*$/i);
            if (!_split)
                throw new ValidationError(
                    $.translatePlaceholders(Laravel.jsLocales.vlsm_error_subnet_line_invalid, {
                        line: line,
                        why: Laravel.jsLocales.vlsm_error_subnet_line_invalid_format,
                    })
                );
            _split = _split.slice(1);
            if (_split.length < 2)
                throw new ValidationError(
                    $.translatePlaceholders(Laravel.jsLocales.vlsm_error_subnet_line_invalid, {
                        line: line,
                        why: Laravel.jsLocales.vlsm_error_subnet_line_invalid_count,
                    })
                );
            this.name = _split[0];

            let _number;
            if (_split[1][0] === '/') {
                const cap = ipv6 ? 128 : 32;
                _number = Math.pow(2, cap - $.rangeLimit(parseInt(_split[1].substring(1)), false, 0, cap));
                _split[2] = 'ip';
            } else _number = parseInt(_split[1]);
            if (/^ips?$/.test(_split[2])) {
                this.minimumPCs = _number - 2;
                this.minimumIPs = _number;
            } else {
                this.minimumPCs = _number;
                this.minimumIPs = _number + 2;
            }
            this.addressCount = fitsIntoPowerOf2(this.minimumIPs);
        }

        toString() {
            return $.translatePlaceholders(Laravel.jsLocales.vlsm_subnet_tostring, {
                name: this.name,
                addresscount: this.addressCount,
                usable: Math.max(0, this.addressCount - 2),
            });
        }
    }

    class SubnetList {
        constructor(netArray, ipv6) {
            this.networks = [];
            $.each(netArray, (_, el) => {
                let sn = new Subnet(el, ipv6);
                if (sn.empty)
                    return;
                this.networks.push(sn);
            });
        }
    }

    class PrefixListEntry {
        constructor(opts) {
            this.seq = parseInt(opts.seq, 10);
            let [min, max] = [1, 4294967294];
            if (this.seq < min || this.seq > max)
                throw new ValidationError(
                    $.translatePlaceholders(Laravel.jsLocales.prefix_list_error_seq_number_invalid, {min, max})
                );

            this.action = opts.action;
            this.subnet = opts.subnet;
            if (typeof opts.le !== 'undefined') {
                this.le = parseInt(opts.le, 10);
                if (this.subnet.mask.length >= this.le)
                    throw new ValidationError(
                        $.translatePlaceholders(Laravel.jsLocales.prefix_list_error_gtlen, {
                            netw: this.subnet,
                            len: this.subnet.mask.length,
                            val: this.le + ' (le)',
                        })
                    );
            } else this.le = null;
            if (typeof opts.ge !== 'undefined') {
                this.ge = parseInt(opts.ge, 10);
                if (this.subnet.mask.length >= this.ge)
                    throw new ValidationError(
                        $.translatePlaceholders(Laravel.jsLocales.prefix_list_error_gtlen, {
                            netw: this.subnet,
                            len: this.subnet.mask.length,
                            val: this.ge + ' (ge)',
                        })
                    );
                if (this.le !== null) {
                    if (this.ge > this.le)
                        throw new ValidationError(
                            $.translatePlaceholders(Laravel.jsLocales.prefix_list_error_ge_le, {
                                netw: this.subnet,
                                len: this.subnet.mask.length,
                                ge: this.ge,
                                le: this.le,
                            })
                        );
                }
            } else this.ge = null;
        }

        /**
         * @param {Network} network
         */
        matches(network) {
            if (this.ge === null && this.le === null)
                return this.subnet.toString() === network.toString();

            const subnetIp = this.subnet.ip.toString();
            if (this.subnet.mask.length === 0)
                network = new Network(subnetIp + '/' + network.mask.length);
            const from = this.le || 32;
            const to = this.ge || 0;
            for (let l = from; l >= to; l--) {
                const ip = network.mask.length === l ? network.ip : network.ip.networkAddress(new Netmask(l));
                console.log(subnetIp, ip.toString(), l, network.mask.length);
                if (subnetIp === ip.toString() && l === network.mask.length)
                    return true;
            }
        }
    }

    let nets = {
        acf: '10.0.0.0/8',
        bc: '172.16.0.0/16',
        bf: '172.16.0.0/12',
        cc: '192.168.0.0/24',
        cf: '192.168.0.0/16',
    };

    $(`.ql-acf, .ql-bc, .ql-bf, .ql-cc, .ql-cf`).on('click', function (e) {
        e.preventDefault();

        let $this = $(this),
            index = $this.attr('class').replace('ql-', ''),
            $input = $this.parents('.form-group').find('input[id]');
        $input.addClass('fade');
        setTimeout(() => {
            $input.val(nets[index]).removeClass('fade').trigger('change');
        }, 105);
    });

    $('.network-input').on('keyup change input', function () {
        const
            $this = $(this),
            $disp = $this.prev().children(),
            ver = ipversion($this.val()) || '?';
        $disp.text("IPv" + ver);
    });

    (function (ns) {
        const $network = $(`#${ns}-network`),
            $subnets = $(`#${ns}-subnets`),
            $networkAlert = $(`#${ns}-network-alert`),
            $subnetsAlert = $(`#${ns}-subnets-alert`),
            $output = $(`#${ns}-output`),
            $outputMode = $(`#${ns}-output-mode`),
            $outputSimple = $(`#${ns}-output-simple`),
            $form = $(`#${ns}-form`),
            lsKey = 'networking-vlsm_output_type';

        let outputMode;
        const setOutputMode = mode => {
            if (mode === 'simple') {
                outputMode = mode;
                if (window.localStorage)
                    localStorage.setItem(lsKey, outputMode);
            } else {
                outputMode = 'fancy';
                if (window.localStorage)
                    localStorage.removeItem(lsKey);
            }
        };
        if (window.localStorage)
            setOutputMode(localStorage.getItem(lsKey));
        const updateOutputDisplay = () => {
            $outputMode.find('a').removeClass('active').filter(`[data-mode="${outputMode}"]`).addClass('active');
            $outputMode.nextAll().addClass('d-none').filter(`.${outputMode}-only`).removeClass('d-none');
        };

        $outputMode.on('click', 'a[data-mode]', e => {
            const $li = $(e.target).closest('a');

            if ($li.hasClass('active'))
                return;

            setOutputMode($li.attr('data-mode'));
            updateOutputDisplay();
        });

        $form.on('submit', function (e) {
            e.preventDefault();

            let network, subnetData;
            $networkAlert.add($subnetsAlert).hide();

            try {
                network = new Network($network.val());
            } catch (err) {
                if (!(err instanceof ValidationError))
                    throw err;
                $networkAlert.children('.text').text(e.message).end().show();
                return $output.addClass('d-none');
            }

            const ipv6 = network.ip instanceof IPV6Address;

            try {
                subnetData = new SubnetList($subnets.val().split('\n'), ipv6);
            } catch (err) {
                if (!(err instanceof ValidationError))
                    throw err;
                $subnetsAlert.children('.text').text(e.message).end().show();
                return $output.addClass('d-none');
            }

            // Sort subnets by number of adresses (descending)
            subnetData.networks = subnetData.networks.sort(function (a, b) {
                if (a.addressCount === b.addressCount)
                    return a.name.localeCompare(b.name);
                return a.addressCount > b.addressCount ? -1 : 1;
            });

            // OUTPUT PHASE
            $output.removeClass('d-none');
            updateOutputDisplay();
            $(`#${ns}-ip-dec`).text(network.ip.toString());
            $(`#${ns}-mask-dec`).html(network.mask.getDecimal() + ' &mdash;&rarr; /' + network.mask.length);
            let $subnetsOutput = $(`#${ns}-subnets-output`).children('tbody').empty(),
                currentNetwork = network,
                lastSubnet = null,
                simpleOutput = [
                    `${Laravel.jsLocales.network}: ${network.ip.toString()}/${network.mask.getAbbrev()}`,
                    Laravel.jsLocales.subnets + ':',
                ],
                bail = false;
            const maxMaskLength = ipv6 ? 128 : 32;
            $.each(subnetData.networks, (i, subnet) => {
                let newip,
                    newmask = maxMaskLength - Math.log2(subnet.addressCount);

                if (i > 0) {
                    let currip = currentNetwork.ip;
                    newip = currip.addBinary(lastSubnet.addressCount).toString();
                } else {
                    if (newmask < currentNetwork.mask.length) {
                        $subnetsAlert.children('.text').text(Laravel.jsLocales.vlsm_network_too_small).end().show();
                        $output.addClass('d-none');
                        return !(bail = true);
                    }
                    newip = network.ip.toString();
                }

                try {
                    currentNetwork = new Network(newip + '/' + newmask);
                } catch (e) {
                    if (!(e instanceof ValidationError))
                        throw e;

                    $subnetsAlert.children('.text').text(e.message).end().show();
                    $output.addClass('d-none');
                    return !(bail = true);
                }
                lastSubnet = subnet;

                simpleOutput.push(`${subnet.name} | ${currentNetwork.ip.toString()}/${currentNetwork.mask.getAbbrev()}`);

                $subnetsOutput.append(
                    `<tr>
						<th rowspan="2" class="bg-primary text-white sn-name">${subnet.name}</th>
						<td>${currentNetwork.ip.toString()}</td>
						<td>
							${currentNetwork.mask.getDecimal()} &mdash;&rarr; /${currentNetwork.mask.length}<br>
							(${Laravel.jsLocales.vlsm_mask_reverse}: ${currentNetwork.mask.getReverseDecimal()})
						</td>
					</tr>
					<tr class="table-info">
						<td colspan="2">
							${$.translatePlaceholders(Laravel.jsLocales.vlsm_info_line, {
                        pcs: subnet.minimumPCs,
                        ips: subnet.minimumIPs,
                    })}
							&mdash;&rarr;
							${$.translatePlaceholders(Laravel.jsLocales.vlsm_info_line, {
                        pcs: subnet.addressCount - 2,
                        ips: subnet.addressCount,
                    })}
						</td>
					</tr>`
                );
            });

            if (!bail)
                $outputSimple.html($('<div class="card-body"/>').text(simpleOutput.join('\n')));
        }).on('reset', function () {
            $output.addClass('d-none');
            $(this).find('.network-input').val('').trigger('change');
        });

        $(`#${ns}-predefined-data, #${ns}-predefined-data-v6`).on('click', function (e) {
            e.preventDefault();

            const ipv6 = this.id.indexOf('-v6') !== -1;

            $network.val(ipv6 ? '2001:db8:85a3::1/64' : '193.30.30.0/24').trigger('change');
            $subnets.val(
                'A 30\n' +
                'B 60\n' +
                'C-VLAN1 10\n' +
                'D-VLAN30 14\n' +
                'E-VLAN40 14\n' +
                `F /${ipv6 ? 126 : 30}\n` +
                `G /${ipv6 ? 126 : 30}`
            ).trigger('change');
            $form.triggerHandler('submit');
        });
    })('vlsm');

    (function (ns) {
        let $network = $(`#${ns}-network`),
            $subnets = $(`#${ns}-subnets`),
            $form = $(`#${ns}-form`),
            $output = $(`#${ns}-output`),
            $networkAlert = $(`#${ns}-network-alert`),
            $subnetsAlert = $(`#${ns}-subnets-alert`);

        $form.on('submit', function (e) {
            e.preventDefault();

            let network, subnetCount;
            $networkAlert.add($subnetsAlert).hide();

            try {
                network = new Network($network.val());
            } catch (err) {
                if (!(err instanceof ValidationError))
                    throw err;
                $networkAlert.children('.text').text(e.message).end().show();
                return $output.empty();
            }
            try {
                subnetCount = parseInt($subnets.val().trim(), 10);
                if (isNaN(subnetCount))
                    throw new ValidationError(Laravel.jsLocales.cidr_error_subnet_line_invalid_format);
            } catch (err) {
                if (!(err instanceof ValidationError))
                    throw err;
                $subnetsAlert.children('.text').text(e.message).end().show();
                return $output.empty();
            }

            const ipv6 = network.ip instanceof IPV6Address;

            let actualSubnetCount = fitsIntoPowerOf2(subnetCount),
                extraBits = Math.log2(actualSubnetCount),
                newmask = network.mask.length + extraBits;
            if (newmask > (ipv6 ? 127 : 31) || newmask < 1) {
                $subnetsAlert.children('.text').text(Laravel.jsLocales.vlsm_error_mask_length_overflow).end().show();
                return $output.empty();
            }

            network.setMask(newmask, ipv6);

            let output = [
                network.toString(),
            ];

            for (let i = 0; i < subnetCount - 1; i++) {
                network.ip.incrRange(network.mask.length - extraBits, extraBits, 1);
                output.push(network.toString());
            }

            let $ol = $.mk('ol');
            $.each(output, (_, el) => {
                $ol.append($.mk('li').text(el));
            });
            $output.html($(`<div class="card-body"/>`).html($ol));
        }).on('reset', function () {
            $output.empty();
            $(this).find('.network-input').val('').trigger('change');
        });

        $(`#${ns}-predefined-data, #${ns}-predefined-data-v6`).on('click', function (e) {
            e.preventDefault();

            $network.val(this.id.indexOf('-v6') === -1 ? '10.0.0.0/8' : '2001:db8:85a3::1/64').trigger('change');
            $subnets.val('11').trigger('change');
            $form.triggerHandler('submit');
        });
    })('cidr');

    (function (ns) {
        const
            $networks = $(`#${ns}-networks`),
            $form = $(`#${ns}-form`),
            $output = $(`#${ns}-output`),
            $networksAlert = $(`#${ns}-networks-alert`);

        $form.on('submit', function (e) {
            e.preventDefault();

            let networks = $networks.val().trim();
            if (!networks)
                return $output.empty();
            networks = networks.split('\n');

            const ipv6 = ipversion(networks[0]) === '6';
            const addresses = {};
            $.each(networks, (_, network) => {
                let address;
                try {
                    address = new Network(network);
                    if ((ipv6 && address.ip instanceof IPV4Address) || (!ipv6 && address.ip instanceof IPV6Address)) {
                        $networksAlert.children('.text').text(Laravel.jsLocales.summary_error_mixed_ip_versions).end().show();
                        return $output.empty();
                    }
                } catch (err) {
                    if (!(err instanceof ValidationError))
                        throw err;
                    $networksAlert.children('.text').text(err.message).end().show();
                    return $output.empty();
                }
                addresses[address.ip.getBinary()] = true;
            });

            let uniqAddrs = Object.keys(addresses).sort();
            const uniqAddrCnt = uniqAddrs.length;
            if (uniqAddrCnt < 2) {
                $networksAlert.children('.text').text(Laravel.jsLocales.summary_not_enough_addresses).end().show();
                return $output.empty();
            }
            const maxLength = ipv6 ? 128 : 32;
            let bit = 0;
            loop:
                for (; bit < maxLength; bit++) {
                    const currbit = uniqAddrs[0][bit];
                    for (let i = 1; i < uniqAddrCnt; i++) {
                        if (currbit !== uniqAddrs[i][bit])
                            break loop;
                    }
                }

            if (bit === 0) {
                $networksAlert.children('.text').text(Laravel.jsLocales.summary_uncommon).end().show();
                return $output.empty();
            }

            $output.html(`<div class="card-body">${(ipv6 ? IPV6Address : IPV4Address).fromBinary(uniqAddrs[0]) + '/' + bit}</div>`);
        }).on('reset', function () {
            $output.empty();
        });

        $(`#${ns}-predefined-data, #${ns}-predefined-data-v6`).on('click', function (e) {
            e.preventDefault();

            $networks.val(this.id.indexOf('-v6') === -1
                ? `172.16.32.0/24\n172.16.40.0/24\n172.16.44.0/24\n172.16.46.0/24`
                : '2001:db8:acad:10::/64\n2001:db8:acad:11::/64\n2001:db8:acad:12::/64\n2001:db8:acad:13::/64'
            ).trigger('change');
            $form.triggerHandler('submit');
        });
    })('summary');

    (function (ns) {
        const
            $showOutput = $(`#${ns}-show-output`),
            $showOutputAlert = $(`#${ns}-show-output-alert`),
            $network = $(`#${ns}-network`),
            $networkAlert = $(`#${ns}-network-alert`),
            $form = $(`#${ns}-form`),
            $output = $(`#${ns}-output`),
            prefixListEntryPattern = ' *seq (-?\\d+) (permit|deny) ([\\d.]+)\\/(\\d{1,2})(?: ge (\\d{1,2}))?(?: le (\\d{1,2}))?',
            prefixListEntryRegex = new RegExp(prefixListEntryPattern, 'g'),
            prefixListRegex = new RegExp(`^ip prefix-list [a-zA-Z\\d_-]{1,63}: \\d+ entries(?:\\n(?:${prefixListEntryPattern}|description .{1,80}))+$`);

        $showOutput.attr('pattern', prefixListRegex.source);

        $form.on('submit', function (e) {
            e.preventDefault();

            let network = $network.val().trim();
            if (!network)
                return $output.empty();

            if (ipversion(network) === '6') {
                $networkAlert.children('.text').text(Laravel.jsLocales.error_ipv4_only).end().show();
                return $output.empty();
            }
            try {
                network = new Network(network);
            } catch (err) {
                if (!(err instanceof ValidationError))
                    throw err;
                $networkAlert.children('.text').text(err.message).end().show();
                return $output.empty();
            }
            $networkAlert.hide();

            const showOutput = $showOutput.val();
            const entries = {};
            if (!prefixListRegex.test(showOutput)) {
                $showOutputAlert.children('.text').text(Laravel.jsLocales.prefix_list_error_show_invalid).end().show();
                return $output.empty();
            }
            let parts;
            while ((parts = prefixListEntryRegex.exec(showOutput)) !== null) {
                let [seq, action, netAddress, maskLength, ge, le] = parts.slice(1);

                try {
                    let subnet = new Network(netAddress + '/' + maskLength);
                    entries[seq] = new PrefixListEntry({seq, action, subnet, ge, le});
                } catch (err) {
                    if (!(err instanceof ValidationError))
                        throw err;
                    $showOutputAlert.children('.text').text($.translatePlaceholders(Laravel.jsLocales.prefix_list_error_seq_invalid, {seq}) + ': ' + err.message).end().show();
                    return $output.empty();
                }
            }
            $showOutputAlert.hide();

            const keys = Object.keys(entries).sort();
            console.log(entries, keys);
            let matchingEntry = null;
            for (let i = 0; i < keys.length; i++) {
                const entry = entries[keys[i]];
                if (entry.matches(network)) {
                    matchingEntry = entry;
                    break;
                }
            }

            if (matchingEntry) {
                $output.html(`<div class="alert alert-success">${$.translatePlaceholders(Laravel.jsLocales.prefix_list_match, {
                    action: `<strong class="text-${matchingEntry.action === 'permit' ? 'success' : 'danger'}">${matchingEntry.action}</strong>`,
                    seq: matchingEntry.seq,
                })}</div>`);
            } else $output.html(`<div class="alert alert-danger">${$.translatePlaceholders(Laravel.jsLocales.prefix_list_nomatch)}</div>`);
        }).on('reset', function () {
            $output.empty();
        });

        $(`#${ns}-predefined-data`).on('click', function (e) {
            e.preventDefault();

            $network.val($network.attr('placeholder')).trigger('change');
            $showOutput.val($showOutput.attr('placeholder')).trigger('change');
            $form.triggerHandler('submit');
        });
    })('prefix-list');

    (function (ns) {
        let $tbody = $(`#${ns}-tbody`);

        for (let i = 0; i <= 32; i++) {
            let mask = new Netmask(i, true);
            $tbody.append(
                `<tr>
					<td>/${i}</td>
					<td>${mask.getDecimal()}</td>
					<td>${mask.getReverseDecimal()}</td>
				</tr>`);
        }

    })('masktable');
})(jQuery);
