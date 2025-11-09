//
fetch('data/patients_record.json')
    .then(response => response.json())
    .then(data => console.log(data));
