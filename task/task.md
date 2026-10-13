<!-- 1. Export feature based on the filtered data  -->
<!-- 2. Add filter panel (My Assigned items) -->
3. AI should answer all questions related to the inventory 
<!-- 4. Stock in import excel file -->
<!-- 5. API dynamic -->
6. what is parameter?
7. print pdf format (if applicable, and based on the school)
8. use phpmailer



# Task 1

Description:

Improve the AI Inventory Assistant so it can correctly handle questions containing multiple related requests, even when they involve both inventory management and demand forecasting or procurement.

The system should identify and separate each part of the user's question, determine which parts belong to the regular inventory assistant and which belong to the forecasting or procurement functionality, and process each through the appropriate existing service. It should combine the results into one coherent answer while preserving the relationship between the questions.

For example, when a user asks, “Why is printer stock low, and when should we restock?”, the assistant should retrieve relevant inventory and transaction records to explain the stock situation, then use the appropriate forecasting functionality to answer the restocking question.

Avoid routing the entire message to a single handler when it contains independently answerable requests. Preserve existing authorization checks, data validation, and separation between the assistant and forecasting modules.


# Task 2
**Description:**

Enhance the AI Inventory Assistant with a controlled multi-step retrieval process that can investigate complex inventory-related questions using multiple approved backend tools.

Instead of executing a request only once, the assistant should be able to identify the information needed, select authorized tools, retrieve database records, evaluate whether the results sufficiently answer the question, and perform additional retrieval steps when necessary.

For example, when asked, “Which classrooms have recurring equipment problems, and which items may need replacement?”, the assistant could retrieve maintenance records, associate equipment with classroom locations, examine available condition and acquisition information, and retrieve maintenance costs if those records exist.

Implement a bounded planning and execution process with predefined tools, validated parameters, authorization checks at every step, and limits on the number of retrieval operations. The assistant must not execute arbitrary SQL or access unauthorized data. If necessary evidence is missing, it should clearly state the limitation rather than invent conclusions.


# Task 3
**Description:**

Improve the AI Inventory Assistant's conversation memory so it can maintain relevant context throughout longer conversations, understand follow-up questions, and correctly distinguish between continuing an existing topic and starting a new one.

The system should preserve relevant information such as the current topic, referenced inventory items, selected locations, date ranges, previously resolved ambiguities, and important results needed for follow-up questions. When a user asks questions such as “Why is it low?”, “Where is that item?”, or “What about the last three months?”, the assistant should resolve the references using conversation context and retrieve current database information where needed.

Ensure that normal and compound conversation turns are recorded consistently in durable server-side history. Improve context reconstruction when session state expires, while respecting configured retention limits and user/session ownership.

Do not rely solely on browser local storage as the source of AI context. Revalidate remembered entities against current database records and permissions, and avoid treating old inventory values as current facts.


# Task 4 
**Description:**

Expand the AI Inventory Assistant's approved retrieval tools to support more detailed, evidence-based analysis of school inventory records over time.

Add support for validated historical date ranges, inventory movement and stock-out trends, maintenance frequency, recurring equipment problems, room or building comparisons, equipment condition, acquisition age, and maintenance costs when the required data is available.

For example, the assistant should be able to answer questions such as “Which rooms had the most maintenance issues in the last three months?”, “Which items frequently require repair?”, and “Based on the available records, should this equipment be repaired or considered for replacement?”

Use database queries and explicit calculations to produce the underlying evidence. Repair-versus-replace recommendations must consider available condition, age, repair history, costs, and applicable decision criteria. Clearly identify missing data and uncertainty rather than presenting unsupported conclusions.

Keep these analytics read-only unless an existing authorized workflow explicitly permits changes. Do not duplicate the existing demand forecasting or procurement logic; integrate with those services where appropriate.


# Task 5
**Description:**

Improve data privacy and information security by ensuring that the AI Inventory Assistant sends Gemini only the minimum database information required to interpret or answer each request.

Review the data returned by every assistant tool and the fact packets passed to the answer-generation service. Exclude unnecessary descriptions, internal maintenance notes, personal information, unrelated records, and other fields that are not required for the user's specific question.

Define explicit, capability-specific data fields for each tool and response type. Apply filtering on the Laravel backend before sending information to Gemini, rather than relying only on prompt instructions asking the model to ignore unnecessary fields.

Preserve the accuracy of answers by retaining the relevant quantities, statuses, dates, locations, and supporting evidence needed for the requested task. Maintain server-side authorization checks, avoid logging secrets or unnecessary sensitive data, and ensure that the assistant's deterministic fallback can still answer using the authorized fact packet.


