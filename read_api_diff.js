const fs = require('fs');
const lines = fs.readFileSync('api_diff.txt', 'utf8').split('\n');

for(let i=0; i<lines.length; i++) {
    if (lines[i].startsWith('+') && !lines[i].startsWith('+++')) {
        console.log(i + ': ' + lines[i]);
    }
}
