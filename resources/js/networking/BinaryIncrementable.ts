import { pad } from '../utils';

export abstract class BinaryIncrementable {
  incrRange(start: number, length: number, by = 1): this {
    let address = this.getBinary();
    const range = address.substring(start, start + length);
    const incremented = pad((parseInt(range, 2) + by).toString(2), '0', length);
    if (incremented.length > length) throw new Error('Incremented length exceeds available space');
    address = address.substring(0, start)
      + incremented
      + address.substring(start + Math.max(length, incremented.length));
    this.setBinary(address);

    return this;
  }

  abstract getBinary(): string;

  abstract setBinary(binary: string): void;

  /**
   * https://stackoverflow.com/a/42306236/1344955
   */
  addBinary(amount: string | number): this {
    const original = this.getBinary();
    let localAmount = typeof amount !== 'string' ? amount.toString(2) : amount;
    if (localAmount.length < original.length) localAmount = pad(localAmount, '0', original.length);
    let i = original.length - 1;
    let j = localAmount.length - 1;
    let carry = 0;
    let result = '';
    /* eslint-disable no-bitwise */
    while (i >= 0 || j >= 0) {
      const m = i < 0 ? 0 : Number(original[i]) | 0;
      const n = j < 0 ? 0 : Number(localAmount[j]) | 0;
      carry += m + n; // sum of two digits
      result = (carry % 2) + result; // string concat
      carry = carry / 2 | 0; // remove decimals,  1 / 2 = 0.5, only get 0
      i--;
      j--;
    }
    /* eslint-enable no-bitwise */
    if (carry !== 0) result = carry + result;

    this.setBinary(result);
    return this;
  }
}
