//
// input_validations.js
//
// Javascript file containing form entry validation functions for the client side.
//
// Author: Rob Howe
//

  function MakeUpperCase(entry) {
    entry.value = entry.value.toUpperCase();
  }

  function LTrim(inputVal) {
    var inputStr = inputVal.toString();
    while (inputStr.charAt(0) == " ") {
      inputStr = inputStr.substr(1);
    }
    return inputStr;
  }
  function RTrim(inputVal) {
    var inputStr = inputVal.toString();
    while (inputStr.charAt(inputStr.length-1) == " ") {
      inputStr = inputStr.substr(0,inputStr.length-1);
    }
    return inputStr;
  }
  function LRTrim(inputVal) {
    return RTrim(LTrim(inputVal));
  }
  function IsEmpty(inputStr) {
    if (inputStr == null || inputStr == "") {
      return true;
    }
    return false;
  }

  function IsInteger(inputVal) {
    inputStr = inputVal.toString();
    for (var loop=0; loop<inputStr.length; loop++) {
      var oneChar = inputStr.charAt(loop);
      if ((loop == 0) && (oneChar == "-")) {
        continue;
      }
      if ((oneChar < "0") || (oneChar > "9")) {
        return false;
      }
    }
    return true;
  }
  function IsPosInteger(inputVal) {
    inputStr = inputVal.toString();
    for (var loop=0; loop<inputStr.length; loop++) {
      var oneChar = inputStr.charAt(loop);
      if (oneChar < "0" || oneChar > "9") {
        return false;
      }
    }
    return true;
  }
  function IsAlphaNumeric(inputVal) {
    inputStr = inputVal.toString();
    for (var loop=0; loop<inputStr.length; loop++) {
      var oneChar = inputStr.charAt(loop);
      if (oneChar < "A" || oneChar > "Z") {
        if (oneChar < "a" || oneChar > "z") {
          if (oneChar < "0" || oneChar > "9") {
            if ((oneChar != "-") && (oneChar != "_")) {
              return false;
            }
          }
        }
      }
    }
    return true;
  }
  function IsAlphaOnly(inputVal) {
    inputStr = inputVal.toString();
    for (var loop=0; loop<inputStr.length; loop++) {
      var oneChar = inputStr.charAt(loop);
      if (oneChar < "A" || oneChar > "Z") {
        if (oneChar < "a" || oneChar > "z") {
          return false;
        }
      }
    }
    return true;
  }

  function IsValidEmailAddress(inputVal) {
    inputStr = LRTrim(inputVal.toString());
    if (inputStr.length < 5) {
      return false;  // Can't be empty or too short
    }
    if (inputStr.indexOf(' ') != -1) {
      return false;  // Can't have spaces
    }
    if (inputStr.indexOf('@') < 1) {
      return false;  // Doesn't have an @
    }
    if (inputStr.lastIndexOf('@') != inputStr.indexOf('@')) {
      return false;  // Contains 2 @'s
    }
    if (inputStr.lastIndexOf('.') < inputStr.indexOf('@')) {
      return false;  // Must have a "." in domain section
    }
    if (inputStr.lastIndexOf('.') == inputStr.length) {
      return false;  // Can't have a "." at the very end
    }
    if (inputStr.charAt(inputStr.indexOf('@') + 1) == ".") {
      return false;  // Can't have a "." immediately after the @
    }

    return true;
  }

  function IsValidSelect(select) {
    if (select.options.selectedIndex < 0) {
      return false;
    }
    if (IsEmpty(select.options[select.options.selectedIndex].value)) {
      return false;
    }
    return true;
  }
