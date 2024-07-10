# AI Interpolator OpenAI (GPT)
## What is the AI Interpolator OpenAI (GPT) module?
This module adds the possibility to add OpenAI GPT responses to interpolate
entities. It's a submodule to the AI Interpolator module.

It currently exposes field plugins for the following field types:
* Boolean
* Link
* E-Mail
* List (Float)
* List (Integer)
* Integer
* Decimal
* Float
* Image
* Taxonomy term
* List (Text)
* Text (Formatted)
* Text (Formatted long)
* Text (Formatted long, with summary)
* Text (Plain)
* Text (Plain long)

### Dependencies
* [OpenAI / ChatGPT /AI Search Integration](https://www.drupal.org/project/openai)

### How to install the AI Interpolator module
1. Get the code like any other module.
2. Install the module.
3. Setup the OpenAI API Key in the OpenAI module.

### How to use the AI Interpolator module
1. Go to any field config listed above.
2. Enable it by checking `Enable AI Interpolator`
3. A sub form will open up where you can choose the Interpolator rule to use
based on your sub-module.
4. Follow the instructions of the forms.
5. Create a content with the field and fill out the base field used for
generation.
6. Your field with the field config you choose should be autopopulated.
