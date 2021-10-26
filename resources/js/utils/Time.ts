import { format, formatRelative, isValid } from 'date-fns';
import { dateFnsLocale, isEnglishLocale } from './locale';

// TODO Fix for date-fns
const dateFormatWithoutWeekday = `${isEnglishLocale ? 'do MMMM yyyy' : 'yyyy. MMMM do'}, `
  + `${isEnglishLocale ? 'h' : 'H'}:mm:ss${isEnglishLocale ? ' aaa' : ''}`;
const dateformat = {
  order: dateFormatWithoutWeekday,
  orderwd: `EEEE, ${dateFormatWithoutWeekday}`,
};

interface Difference {
  past: boolean;
  time: number;
  target: Date;
  hour: number;
  minute: number;
  second: number;
  day: number;
  week: number;
  year: number;
  month: number;
}

export class Time {
  static inSeconds = {
    year: 31_557_600,
    month: 2_592_000,
    week: 604_800,
    day: 86400,
    hour: 3600,
    minute: 60,
  };

  static update(): void {
    $('time[datetime]:not(.nodt)')
      .addClass('dynt')
      .each(function () {
        const $this = $(this);
        const date = $this.attr('datetime');
        if (typeof date !== 'string') throw new TypeError(`Invalid date data type: "${typeof date}"`);

        const timestamp = new Date(date);
        if (!isValid(timestamp)) throw new Error(`Invalid date format: "${date}"`);

        const now = new Date();
        const showDayOfWeek = !$this.attr('data-noweekday');
        const timeAgoStr = formatRelative(timestamp, now, { locale: dateFnsLocale });
        const $elapsedHolder = $this.parent()
          .children('.dynt-el');
        const updateHandler = $this.data('dyntime-beforeupdate');

        if (typeof updateHandler === 'function') {
          const result = updateHandler(Time.difference(now, timestamp));
          if (result === false) return;
        }

        if ($elapsedHolder.length > 0 || $this.hasClass('no-dynt-el')) {
          $this.html(format(timestamp, showDayOfWeek ? dateformat.orderwd : dateformat.order, { locale: dateFnsLocale }));
          $elapsedHolder.html(timeAgoStr);
        } else $this.attr('title', format(timestamp, dateformat.order, { locale: dateFnsLocale }))
          .html(timeAgoStr);
      });
  }

  static difference(now: Date, timestamp: Date): Difference {
    const subtract = (now.getTime() - timestamp.getTime()) / 1000;
    let time = Math.abs(subtract);
    let week = 0;
    let month = 0;
    let year = 0;

    let day = Math.floor(time / Time.inSeconds.day);
    time -= day * Time.inSeconds.day;

    const hour = Math.floor(time / Time.inSeconds.hour);
    time -= hour * Time.inSeconds.hour;

    const minute = Math.floor(time / Time.inSeconds.minute);
    time -= minute * Time.inSeconds.minute;

    const second = Math.floor(time);

    if (day >= 7) {
      week = Math.floor(day / 7);
      day -= week * 7;
    }
    if (week >= 4) {
      month = Math.floor(week / 4);
      week -= month * 4;
    }
    if (month >= 12) {
      year = Math.floor(month / 12);
      month -= year * 12;
    }

    return {
      past: subtract > 0,
      time,
      target: timestamp,
      week,
      month,
      year,
      day,
      hour,
      minute,
      second,
    };
  }
}

Object.assign(window, { Time });

Time.update();
setInterval(Time.update, 10e3);
