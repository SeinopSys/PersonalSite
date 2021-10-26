import { format, utcToZonedTime } from 'date-fns-tz';
import { FunctionComponent } from 'preact';
import { useEffect, useMemo, useState } from 'preact/compat';
import { isEnglishLocale } from '../utils/locale';

const LOCAL_TIME_ZONE = 'Europe/Budapest';

export const LocalTime: FunctionComponent = () => {
  const [date, setDate] = useState(() => new Date());

  const transformedDate = useMemo(() => utcToZonedTime(date, LOCAL_TIME_ZONE), [date]);

  useEffect(() => {
    const updateId = setInterval(() => {
      setDate(new Date());
    }, 1000);

    return () => clearInterval(updateId);
  });

  return (
    <>
      {transformedDate.getHours()}
      <span className="tick">:</span>
      {format(transformedDate, isEnglishLocale ? 'mm a' : 'mm')}
    </>
  );
};
