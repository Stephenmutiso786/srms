$(document).ready(function() {
$(".app_frm").on("submit", function(){
$(".app_btn").blur();
var current_effect = 'bounce';
run_waitMe(current_effect);
function run_waitMe(effect){
$('.app_frm').waitMe({

effect: 'ios',
text: '',

bg: 'rgba(255,255,255,0.6)',

color: '#00695c',

maxSize: '',

waitTime: -1,
source: '',

textPos: 'vertical',

fontSize: '',
onClose: function() {}

});
}
})
});

function del(url, text) {
Swal.fire({
title: 'Confirmation',
text: text,
icon: 'question',
showDenyButton: true,
confirmButtonText: 'No',
denyButtonText: 'Yes'
}).then((result) => {
if (result.isDenied) {
window.location.href = url;
} else if (result.Confirmed) {
}
});

}

function set_term(id, status) {
document.getElementById('term').value = document.getElementById('term_'+id).value;
document.getElementById('status').value = status;
document.getElementById('id').value = id;
}

function set_user(id, gender, status) {
document.getElementById('fname').value = document.getElementById('fname_'+id).value;
document.getElementById('lname').value = document.getElementById('lname_'+id).value;
document.getElementById('email').value = document.getElementById('email_'+id).value;
document.getElementById('gender').value = gender;
document.getElementById('status').value = status;
document.getElementById('id').value = id;
}

function set_class(id) {
document.getElementById('name').value = document.getElementById('class_'+id).value;
document.getElementById('id').value = id;
}

function set_subject(id) {
document.getElementById('name').value = document.getElementById('subject_'+id).value;
document.getElementById('id').value = id;
}

function set_combination(id) {

var loader = "<div class='d-flex justify-content-center'><div class='spinner-border' style='height:16px; width:16px; margin-top:2px;'  role='status'><span class='sr-only'></span></div>&nbsp;Fetching information, please wait..</div>";
document.getElementById('comb_feedback').innerHTML = '<div class="alert alert-dismissible alert-info"><strong>'+loader+'</strong> </div>';

$.ajax({
type: 'POST',
url: 'app/ajax/fetch_combination.php',
data: 'id=' + id + '&submit=1',
success: function (comb_data) {
document.getElementById('comb_feedback').innerHTML = comb_data;

$('.select3').select2({
dropdownParent: $("#editModal")
})


}
});

}

function set_grade(id) {
document.getElementById('grade').value = document.getElementById('grade_'+id).value;
document.getElementById('min').value = document.getElementById('min_'+id).value;
document.getElementById('max').value = document.getElementById('max_'+id).value;
document.getElementById('remark').value = document.getElementById('remark_'+id).value;
document.getElementById('id').value = id;
}

function set_announcement(id) {
var loader = "<div class='d-flex justify-content-center'><div class='spinner-border' style='height:16px; width:16px; margin-top:2px;'  role='status'><span class='sr-only'></span></div>&nbsp;Fetching information, please wait..</div>";
document.getElementById('ajax_callback').innerHTML = '<div class="alert alert-dismissible alert-info"><strong>'+loader+'</strong> </div>';

$.ajax({
type: 'POST',
url: 'app/ajax/fetch_announcement.php',
data: 'id=' + id + '&submit=1',
success: function (announcement_data) {
document.getElementById('ajax_callback').innerHTML = announcement_data;

$('#summernote2').summernote({
tabsize: 2,
height: 120,
fontNames: ['Comic Sans MS']
});

}
});
}

function fetch_subjects(class_id) {
var current_effect = 'bounce';
run_waitMe(current_effect);
function run_waitMe(effect){
$('.app_frm').waitMe({

effect: 'ios',
text: 'Fetching Subjects....',

bg: 'rgba(255,255,255,0.6)',

color: '#00695c',

maxSize: '',

waitTime: -1,
source: '',

textPos: 'vertical',

fontSize: '',
onClose: function() {}

});
}

$('#sub_imp').find('option').remove();

$.ajax({
type: 'POST',
url: 'app/ajax/fetch_subjects.php',
data: 'id=' + class_id + '&submit=1',
success: function (data) {
$('#sub_imp').append(data)
$('.app_frm').waitMe('hide');
}
});

}

$("#sub_btnp").on("click", function(){
$("#sub_btnp").blur();
var current_pw = document.getElementById('cpass').value;
var new_pw = document.getElementById('npass').value;
var confirm_pw = document.getElementById('cnpass').value;

if (current_pw == "") {

Swal.fire({
title: 'Please enter your current password',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;
}

if (new_pw == "") {

Swal.fire({
title: 'Please enter your new password',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;

}

if((new_pw).length < 8)
{

Swal.fire({
title: 'New password should be minimum 8 characters',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;


}

if (confirm_pw == "") {

Swal.fire({
title: 'Please enter confirmation password',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;

}

if((confirm_pw).length < 8)
{

Swal.fire({
title: 'Confirmation password should be minimum 8 characters',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;

}


if(confirm_pw != new_pw)
{

Swal.fire({
title: 'Password confirmation does not match',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;



}

var pattern = /^(?=.{5,})(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[\W])/

var checkval = pattern.test($("#npass").val());
if(!checkval) {

Swal.fire({
title: 'Password must contain At least one uppercase letter, one lowercase letter, one digit, one special symbol',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;

}

})


$("#sub_btnp2").on("click", function(){
$("#sub_btnp2").blur();
var new_pw = document.getElementById('npass').value;
var confirm_pw = document.getElementById('cnpass').value;

if (new_pw == "") {

Swal.fire({
title: 'Please enter password',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;

}

if((new_pw).length < 8)
{

Swal.fire({
title: 'Password should be minimum 8 characters',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;


}

if (confirm_pw == "") {

Swal.fire({
title: 'Please enter confirmation password',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;

}

if((confirm_pw).length < 8)
{

Swal.fire({
title: 'Confirmation password should be minimum 8 characters',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;

}


if(confirm_pw != new_pw)
{

Swal.fire({
title: 'Password confirmation does not match',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;



}

var pattern = /^(?=.{5,})(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[\W])/

var checkval = pattern.test($("#npass").val());
if(!checkval) {

Swal.fire({
title: 'Password must contain At least one uppercase letter, one lowercase letter, one digit, one special symbol',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})
return false;

}


})

function set_division(id) {
document.getElementById('division').value = document.getElementById('division_'+id).value;
document.getElementById('min').value = document.getElementById('min_'+id).value;
document.getElementById('max').value = document.getElementById('max_'+id).value;
document.getElementById('min2').value = document.getElementById('min2_'+id).value;
document.getElementById('max2').value = document.getElementById('max2_'+id).value;
document.getElementById('points').value = document.getElementById('points_'+id).value;
document.getElementById('id').value = id;
}

function set_student(id) {
document.getElementById('fname').value = document.getElementById('fname_'+id).value;
document.getElementById('mname').value = document.getElementById('mname_'+id).value;
document.getElementById('lname').value = document.getElementById('lname_'+id).value;
document.getElementById('gender').value = document.getElementById('gender_'+id).value;
document.getElementById('class').value = document.getElementById('class_'+id).value;
document.getElementById('email').value = document.getElementById('email_'+id).value;
document.getElementById('photo').value = document.getElementById('img_'+id).value;
document.getElementById('id').value = id;

$('.select2').select2({
dropdownParent: $("#editModal")
})

}


function lettersOnly()
{
var charCode = event.keyCode;

if ((charCode > 64 && charCode < 91) || (charCode > 96 && charCode < 123) || charCode == 8)

return true;
else

Swal.fire({
title: 'Numbers are not allowed in names',
icon: 'error',
showDenyButton: false,
confirmButtonText: 'Okay',
})

return false;
}
