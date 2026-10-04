/* eslint-disable no-console */
function step(rotationDegrees) {
  return (rotationDegrees + 90) % 360;
}

let r = 0;
const seq = [];
for (let i = 0; i < 5; i += 1) {
  r = step(r);
  seq.push(r);
}

const expected = [90, 180, 270, 0, 90];
const ok = JSON.stringify(seq) === JSON.stringify(expected);
console.log(ok ? 'PASS rotate sequence' : 'FAIL rotate sequence', seq);
process.exit(ok ? 0 : 1);
