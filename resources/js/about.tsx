import './utils/preact-debug';
import { render } from 'preact';
import { Age } from './about/Age';
import { LocalTime } from './about/LocalTime';

const localTime = document.getElementById('localtime');
if (localTime) {
  localTime.innerHTML = '';
  render(<LocalTime />, localTime);
}

const age = document.getElementById('age');
if (age) {
  age.innerHTML = '';
  render(<Age birthDateString={age.getAttribute('datetime')} />, age);
}
