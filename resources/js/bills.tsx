import { render } from 'preact';
import { BillsApp } from './bills/BillsApp';

const mount = document.getElementById('bills-app');
if (mount) render(<BillsApp />, mount);
