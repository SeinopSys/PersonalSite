import { fitsIntoPowerOf2, ipversion } from './networking/common';
import { IPV4Address } from './networking/IPV4Address';
import { IPV6Address } from './networking/IPV6Address';
import { Netmask } from './networking/Netmask';
import { Network } from './networking/Network';
import { PrefixListAction, PrefixListEntry } from './networking/PrefixListEntry';
import { Subnet } from './networking/Subnet';
import { SubnetList } from './networking/SubnetList';
import { ValidationError } from './networking/ValidationError';
import { translatePlaceholders } from './utils';

const $tabs = $('#main-tabs');
const hashchange = function (hash: string) {
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
    const $a = $tabs.find('a').first().addClass('active');
    const anchor = $a.attr('href');
    if (anchor) $(anchor).removeClass('d-none');
  }
};
$(window).on('hashchange', () => {
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
  if (e.target.tagName === 'INPUT') isValid = $target.is(':valid');
  else {
    const targetVal = $target.val() as string;
    if (typeof $target.attr('required') !== 'undefined') isValid = targetVal.length > 0;
    else isValid = true;

    if (isValid) {
      const pattern = $target.attr('pattern');
      if (typeof pattern !== 'undefined') {
        isValid = new RegExp(pattern).test(targetVal);
      }
    }
  }
  $target[isValid ? 'removeClass' : 'addClass']('is-invalid');
}).trigger('change');

if (typeof Math.log2 !== 'function') Math.log2 = n => Math.log(n) * Math.LOG2E;

const nets = {
  acf: '10.0.0.0/8',
  bc: '172.16.0.0/16',
  bf: '172.16.0.0/12',
  cc: '192.168.0.0/24',
  cf: '192.168.0.0/16',
} as const;

$('.ql-acf, .ql-bc, .ql-bf, .ql-cc, .ql-cf').on('click', function (e) {
  e.preventDefault();

  const $this = $(this);
  const index = ($this.attr('class') as string).replace('ql-', '');
  const $input = $this.parents('.js-input-wrap').find('input[id]');
  $input.addClass('fade');
  setTimeout(() => {
    $input.val(nets[index as keyof typeof nets]).removeClass('fade').trigger('change');
  }, 105);
});

$('.network-input').on('keyup change input', function () {
  const
    $this = $(this);
  const $disp = $this.prev().children();
  const ver = ipversion($this.val() as string) || '?';
  $disp.text(`IPv${ver}`);
});

(function (ns) {
  const $ipVer = $(`#${ns}-ipver`);
  const $network = $(`#${ns}-network`);
  const $subnets = $(`#${ns}-subnets`);
  const $networkAlert = $(`#${ns}-network-alert`);
  const $subnetsAlert = $(`#${ns}-subnets-alert`);
  const $output = $(`#${ns}-output`);
  const $outputMode = $(`#${ns}-output-mode`);
  const $outputSimple = $(`#${ns}-output-simple`);
  const $form = $(`#${ns}-form`);
  const lsKey = 'networking-vlsm_output_type';

  let outputMode: 'fancy' | 'simple';
  const setOutputMode = (mode: typeof outputMode) => {
    if (mode === 'simple') {
      outputMode = mode;
      if (window.localStorage) localStorage.setItem(lsKey, outputMode);
    } else {
      outputMode = 'fancy';
      if (window.localStorage) localStorage.removeItem(lsKey);
    }
  };
  if (window.localStorage) setOutputMode(localStorage.getItem(lsKey) as typeof outputMode);
  const updateOutputDisplay = () => {
    $outputMode.find('a').removeClass('active').filter(`[data-mode="${outputMode}"]`).addClass('active');
    $outputMode.nextAll().addClass('d-none').filter(`.${outputMode}-only`).removeClass('d-none');
  };

  $outputMode.on('click', 'a[data-mode]', e => {
    const $li = $(e.target).closest('a');

    if ($li.hasClass('active')) return;

    setOutputMode($li.attr('data-mode') as typeof outputMode);
    updateOutputDisplay();
  });

  $network.on('change', () => {
    let network: Network | undefined;
    try {
      network = new Network($network.val() as string);
    } catch {
      // ignore error
    }
    $ipVer.text(network ? (network.ip instanceof IPV6Address ? 'IPv6' : 'IPv4') : 'IPv?');
  });

  $form.on('submit', e => {
    e.preventDefault();

    let network: Network;
    let subnetData: SubnetList;
    $networkAlert.add($subnetsAlert).hide();

    try {
      network = new Network($network.val() as string);
    } catch (err) {
      if (!(err instanceof ValidationError)) throw err;
      $networkAlert.children('.text').text(err instanceof Error ? err.message : '').end().show();
      $output.addClass('d-none');
      return;
    }

    const ipv6 = network.ip instanceof IPV6Address;

    try {
      subnetData = new SubnetList(($subnets.val() as string).split('\n'), ipv6);
    } catch (err) {
      if (!(err instanceof ValidationError)) throw err;
      $subnetsAlert.children('.text').text(err.message).end().show();
      $output.addClass('d-none');
      return;
    }

    // Sort subnets by number of addresses (descending)
    subnetData.networks = subnetData.networks.sort((a, b) => {
      if (a.addressCount === b.addressCount) return a.name.localeCompare(b.name);
      return a.addressCount > b.addressCount ? -1 : 1;
    });

    // OUTPUT PHASE
    $output.removeClass('d-none');
    updateOutputDisplay();
    $(`#${ns}-ip-dec`).text(network.ip.toString());
    $(`#${ns}-mask-dec`).html(`${network.mask.getDecimal()} &mdash;&rarr; /${network.mask.length}`);
    const $subnetsOutput = $(`#${ns}-subnets-output`).children('tbody').empty();
    let currentNetwork = network;
    let lastSubnet: Subnet | null = null;
    const simpleOutput = [
      `${window.Laravel.jsLocales.network}: ${network.ip.toString()}/${network.mask.getAbbrev()}`,
      `${window.Laravel.jsLocales.subnets}:`,
    ];
    let bail = false;
    const maxMaskLength = ipv6 ? 128 : 32;
    $.each(subnetData.networks, (i, subnet) => {
      let newIp: string;
      const newMask = maxMaskLength - Math.log2(subnet.addressCount);

      if (lastSubnet) {
        const currIp = currentNetwork.ip;
        newIp = currIp.addBinary(lastSubnet.addressCount).toString();
      } else {
        if (newMask < currentNetwork.mask.length) {
          $subnetsAlert.children('.text').text(window.Laravel.jsLocales.vlsm_network_too_small).end().show();
          $output.addClass('d-none');
          bail = true;
          return false;
        }
        newIp = network.ip.toString();
      }

      try {
        currentNetwork = new Network(`${newIp}/${newMask}`);
      } catch (err) {
        if (!(err instanceof ValidationError)) throw err;

        $subnetsAlert.children('.text').text(err instanceof Error ? err.message : '').end().show();
        $output.addClass('d-none');
        bail = true;
        return false;
      }
      lastSubnet = subnet;

      simpleOutput.push(`${subnet.name} | ${currentNetwork.ip.toString()}/${currentNetwork.mask.getAbbrev()}`);

      $subnetsOutput.append(
        `<tr>
            <th rowspan="2" class="bg-primary text-white sn-name">${subnet.name}</th>
            <td>${currentNetwork.ip.toString()}</td>
            <td>
              ${currentNetwork.mask.getDecimal()} &mdash;&rarr; /${currentNetwork.mask.length}<br>
              (${window.Laravel.jsLocales.vlsm_mask_reverse}: ${currentNetwork.mask.getReverseDecimal()})
            </td>
          </tr>
          <tr class="table-info">
            <td colspan="2">
              ${translatePlaceholders(window.Laravel.jsLocales.vlsm_info_line, {
    pcs: subnet.minimumPCs,
    ips: subnet.minimumIPs,
  })}
              &mdash;&rarr;
              ${translatePlaceholders(window.Laravel.jsLocales.vlsm_info_line, {
    pcs: subnet.addressCount - 2,
    ips: subnet.addressCount,
  })}
            </td>
          </tr>`,
      );
      return true;
    });

    if (!bail) $outputSimple.empty().append($('<div class="card-body"/>').text(simpleOutput.join('\n')));
  }).on('reset', function () {
    $output.addClass('d-none');
    $(this).find('.network-input').val('').trigger('change');
  });

  $(`#${ns}-predefined-data, #${ns}-predefined-data-v6`).on('click', function (e) {
    e.preventDefault();

    const ipv6 = this.id.indexOf('-v6') !== -1;

    $network.val(ipv6 ? '2001:db8:85a3::1/64' : '193.30.30.0/24').trigger('change');
    $subnets.val(
      'A 30\n'
      + 'B 60\n'
      + 'C-VLAN1 10\n'
      + 'D-VLAN30 14\n'
      + 'E-VLAN40 14\n'
      + `F /${ipv6 ? 126 : 30}\n`
      + `G /${ipv6 ? 126 : 30}`,
    ).trigger('change');
    $form.triggerHandler('submit');
  });
})('vlsm');

(function (ns) {
  const $ipVer = $(`#${ns}-ipver`);
  const $network = $(`#${ns}-network`);
  const $subnets = $(`#${ns}-subnets`);
  const $form = $(`#${ns}-form`);
  const $output = $(`#${ns}-output`);
  const $networkAlert = $(`#${ns}-network-alert`);
  const $subnetsAlert = $(`#${ns}-subnets-alert`);

  $network.on('change', () => {
    let network: Network | undefined;
    try {
      network = new Network($network.val() as string);
    } catch {
      // ignore error
    }
    $ipVer.text(network ? (network.ip instanceof IPV6Address ? 'IPv6' : 'IPv4') : 'IPv?');
  });

  $form.on('submit', e => {
    e.preventDefault();

    let network: Network;
    let subnetCount: number;
    $networkAlert.add($subnetsAlert).hide();

    try {
      network = new Network($network.val() as string);
    } catch (err) {
      if (!(err instanceof ValidationError)) throw err;
      $networkAlert.children('.text').text(err instanceof Error ? err.message : '').end().show();
      $output.empty();
      return;
    }
    try {
      subnetCount = parseInt(($subnets.val() as string).trim(), 10);
      if (Number.isNaN(subnetCount)) throw new ValidationError(window.Laravel.jsLocales.cidr_error_subnet_line_invalid_format);
    } catch (err) {
      if (!(err instanceof ValidationError)) throw err;
      $subnetsAlert.children('.text').text(err instanceof Error ? err.message : '').end().show();
      $output.empty();
      return;
    }

    const ipv6 = network.ip instanceof IPV6Address;

    const actualSubnetCount = fitsIntoPowerOf2(subnetCount);
    const extraBits = Math.log2(actualSubnetCount);
    const newmask: number = network.mask.length + extraBits;
    if (newmask > (ipv6 ? 127 : 31) || newmask < 1) {
      $subnetsAlert.children('.text').text(window.Laravel.jsLocales.vlsm_error_mask_length_overflow).end().show();
      $output.empty();
      return;
    }

    network.setMask(newmask, ipv6);

    const output = [
      network.toString(),
    ];

    for (let i = 0; i < subnetCount - 1; i++) {
      network.ip.incrRange(network.mask.length - extraBits, extraBits, 1);
      output.push(network.toString());
    }

    const $ol = $(document.createElement('ol'));
    $.each(output, (_, el) => {
      $ol.append($(document.createElement('li')).text(el));
    });
    $output.empty().append($<HTMLDivElement>('<div class="card-body"/>').append($ol));
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
    $networks = $(`#${ns}-networks`);
  const $form = $(`#${ns}-form`);
  const $output = $(`#${ns}-output`);
  const $networksAlert = $(`#${ns}-networks-alert`);

  $form.on('submit', e => {
    e.preventDefault();

    const networks = ($networks.val() as string).trim();
    if (!networks) {
      $output.empty();
      return;
    }
    const splitNetworks = networks.split('\n');

    const ipv6 = ipversion(splitNetworks[0]) === '6';
    const addresses: Record<string, boolean> = {};
    $.each(splitNetworks, (_, network) => {
      let address;
      try {
        address = new Network(network);
        if ((ipv6 && address.ip instanceof IPV4Address) || (!ipv6 && address.ip instanceof IPV6Address)) {
          $networksAlert.children('.text').text(window.Laravel.jsLocales.summary_error_mixed_ip_versions).end().show();
          $output.empty();
          return;
        }
      } catch (err) {
        if (!(err instanceof ValidationError)) throw err;
        $networksAlert.children('.text').text(err.message).end().show();
        $output.empty();
        return;
      }
      addresses[address.ip.getBinary()] = true;
    });

    const uniqAddrs = Object.keys(addresses).sort();
    const uniqAddrCnt = uniqAddrs.length;
    if (uniqAddrCnt < 2) {
      $networksAlert.children('.text').text(window.Laravel.jsLocales.summary_not_enough_addresses).end().show();
      $output.empty();
      return;
    }
    const maxLength = ipv6 ? 128 : 32;
    let bit = 0;
    // eslint-disable-next-line no-labels,no-restricted-syntax
    loop:
    for (; bit < maxLength; bit++) {
      const currBit = uniqAddrs[0][bit];
      for (let i = 1; i < uniqAddrCnt; i++) {
        // eslint-disable-next-line no-labels
        if (currBit !== uniqAddrs[i][bit]) break loop;
      }
    }

    if (bit === 0) {
      $networksAlert.children('.text').text(window.Laravel.jsLocales.summary_uncommon).end().show();
      $output.empty();
      return;
    }

    $output.html(`<div class="card-body">${(ipv6 ? IPV6Address : IPV4Address).fromBinary(uniqAddrs[0])}/${bit}</div>`);
  }).on('reset', () => {
    $output.empty();
  });

  $(`#${ns}-predefined-data, #${ns}-predefined-data-v6`).on('click', function (e) {
    e.preventDefault();

    $networks.val(this.id.indexOf('-v6') === -1
      ? '172.16.32.0/24\n172.16.40.0/24\n172.16.44.0/24\n172.16.46.0/24'
      : '2001:db8:acad:10::/64\n2001:db8:acad:11::/64\n2001:db8:acad:12::/64\n2001:db8:acad:13::/64').trigger('change');
    $form.triggerHandler('submit');
  });
})('summary');

(function (ns) {
  const
    $showOutput = $(`#${ns}-show-output`);
  const $showOutputAlert = $(`#${ns}-show-output-alert`);
  const $network = $(`#${ns}-network`);
  const $networkAlert = $(`#${ns}-network-alert`);
  const $form = $(`#${ns}-form`);
  const $output = $(`#${ns}-output`);
  const prefixListEntryPattern = ' *seq (-?\\d+) (permit|deny) ([\\d.]+)\\/(\\d{1,2})(?: ge (\\d{1,2}))?(?: le (\\d{1,2}))?';
  const prefixListEntryRegex = new RegExp(prefixListEntryPattern, 'g');
  const prefixListRegex = new RegExp(
    `^ip prefix-list [a-zA-Z\\d_-]{1,63}: \\d+ entries(?:\\n(?:${prefixListEntryPattern}|description .{1,80}))+$`,
  );

  $showOutput.attr('pattern', prefixListRegex.source);

  $form.on('submit', e => {
    e.preventDefault();

    const networkStr = ($network.val() as string).trim();
    if (!networkStr) {
      $output.empty();
      return;
    }

    if (ipversion(networkStr) === '6') {
      $networkAlert.children('.text').text(window.Laravel.jsLocales.error_ipv4_only).end().show();
      $output.empty();
      return;
    }
    let network: Network;
    try {
      network = new Network(networkStr);
    } catch (err) {
      if (!(err instanceof ValidationError)) throw err;
      $networkAlert.children('.text').text(err.message).end().show();
      $output.empty();
      return;
    }
    $networkAlert.hide();

    const showOutput = $showOutput.val() as string;
    const entries: Record<string, PrefixListEntry> = {};
    if (!prefixListRegex.test(showOutput)) {
      $showOutputAlert.children('.text').text(window.Laravel.jsLocales.prefix_list_error_show_invalid).end().show();
      $output.empty();
      return;
    }
    let parts;
    // eslint-disable-next-line no-cond-assign
    while ((parts = prefixListEntryRegex.exec(showOutput)) !== null) {
      const [seq, action, netAddress, maskLength, ge, le] = parts.slice(1);

      try {
        const subnet = new Network(`${netAddress}/${maskLength}`);
        entries[seq] = new PrefixListEntry({
          seq,
          action: action as PrefixListAction,
          subnet,
          ge,
          le,
        });
      } catch (err) {
        if (!(err instanceof ValidationError)) throw err;
        $showOutputAlert.children('.text')
          .text(`${translatePlaceholders(window.Laravel.jsLocales.prefix_list_error_seq_invalid, { seq })}: ${err.message}`)
          .end()
          .show();
        $output.empty();
        return;
      }
    }
    $showOutputAlert.hide();

    const keys = Object.keys(entries).sort();
    let matchingEntry = null;
    for (let i = 0; i < keys.length; i++) {
      const entry = entries[keys[i]];
      if (entry.matches(network)) {
        matchingEntry = entry;
        break;
      }
    }

    if (matchingEntry) {
      $output.html(`<div class="alert alert-success">${translatePlaceholders(window.Laravel.jsLocales.prefix_list_match, {
        action: `<strong class="text-${matchingEntry.action === 'permit' ? 'success' : 'danger'}">${matchingEntry.action}</strong>`,
        seq: matchingEntry.seq,
      })}</div>`);
    } else $output.html(`<div class="alert alert-danger">${translatePlaceholders(window.Laravel.jsLocales.prefix_list_nomatch)}</div>`);
  }).on('reset', () => {
    $output.empty();
  });

  $(`#${ns}-predefined-data`).on('click', e => {
    e.preventDefault();

    $network.val($network.attr('placeholder') as string).trigger('change');
    $showOutput.val($showOutput.attr('placeholder') as string).trigger('change');
    $form.triggerHandler('submit');
  });
})('prefix-list');

(function (ns) {
  const $tbody = $(`#${ns}-tbody`);

  for (let i = 0; i <= 32; i++) {
    const mask = new Netmask(i, true);
    $tbody.append(
      `<tr>
          <td>/${i}</td>
          <td>${mask.getDecimal()}</td>
          <td>${mask.getReverseDecimal()}</td>
        </tr>`,
    );
  }
})('masktable');
