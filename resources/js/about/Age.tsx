import { differenceInYears } from 'date-fns';
import { FunctionComponent } from 'preact';
import { useEffect, useMemo, useState } from 'preact/compat';

export const Age: FunctionComponent<{ birthDateString: string | null }> = ({ birthDateString }) => {
  const [now, setNow] = useState(() => new Date());
  const birthDate = useMemo(() => birthDateString && new Date(birthDateString), [birthDateString]);

  useEffect(() => {
    const updateId = setInterval(() => {
      setNow(new Date());
    }, 10000);

    return () => clearInterval(updateId);
  });

  if (!birthDate) return null;

  return (
    <>{differenceInYears(now, birthDate)}</>
  );
};
