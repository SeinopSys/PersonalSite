import { pad } from '../utils';
import {
  LRC_META_TAGS, LRC_TS_DECIMALS, LRCMetadata, LRCMetadataKeys,
} from './common';

export class MetadataEditingForm {
  private $form: JQuery<HTMLFormElement>;

  constructor(compiledMetadata: LRCMetadata) {
    this.$form = $<HTMLFormElement>(document.createElement('form')).attr('id', 'metadata-editing-form');
    const { metadata_field_placeholders } = window.Laravel.jsLocales;
    $.each(LRC_META_TAGS, (short, long) => {
      const id = `meta_input_${short}`;
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const inputAttrs: any = {
        type: 'text',
        name: short,
        id,
        value: compiledMetadata[short],
        class: 'form-control',
      };
      if (short === 'offset') {
        inputAttrs.type = 'number';
        inputAttrs.step = `0.${pad('1', '0', LRC_TS_DECIMALS)}`;
        inputAttrs.min = '0';
        inputAttrs.placeholder = '0';
        if (inputAttrs.value === inputAttrs.placeholder) inputAttrs.value = '';
      }
      const longKey = (metadata_field_placeholders as unknown as Record<Required<LRCMetadata>[LRCMetadataKeys], string>)[long];
      this.$form.append(
        $(document.createElement('div'))
          .attr('class', 'mb-3')
          .append(
            $(document.createElement('label')).attr({ for: id }).append(longKey, ` <code>${short}</code>`),
            $(document.createElement('input')).attr(inputAttrs),
          ),
      );
    });
    this.$form.prepend(
      $(document.createElement('div'))
        .attr('class', ' alert alert-info text-center')
        .append(
          $(document.createElement('span')).text(window.Laravel.jsLocales.dialog_edit_meta_reset_info),
          document.createElement('br'),
          $(document.createElement('button'))
            .attr({
              class: 'btn btn-info',
              type: 'reset',
            })
            .append(
              '<i class="fa fa-undo me-2"/>',
              $(document.createElement('span')).text(window.Laravel.jsLocales.dialog_edit_meta_reset_btn),
            ),
        ),
    );
  }

  get(): JQuery {
    return this.$form.clone();
  }
}
